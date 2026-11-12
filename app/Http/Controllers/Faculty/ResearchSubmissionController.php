<?php

namespace App\Http\Controllers\Faculty;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractResearchMetadataRequest;
use App\Http\Requests\StoreResearchSubmissionRequest;
use App\Http\Requests\UpdateResearchSubmissionRequest;
use App\Models\AcademicYear;
use App\Models\ResearchAgenda;
use App\Models\ResearchSubmission;
use App\Models\ResearchSubmissionAuthor;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\Sdg;
use App\Models\SubmissionAttachment;
use App\Models\User;
use App\Services\ResearchClaimCalculator;
use App\Services\ResearchPaperMetadataExtractor;
use App\Services\ResearchSubmissionAuthorService;
use App\Services\ResearchSubmissionDuplicateService;
use App\Services\ResearchWorkflowService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchSubmissionController extends Controller
{
    public function __construct(
        private readonly ResearchWorkflowService $workflow,
        private readonly ResearchSubmissionAuthorService $authorService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ResearchSubmission::class);

        $submissions = ResearchSubmission::query()
            ->where(function ($query) use ($request) {
                $query->where('faculty_id', $request->user()->id)
                    ->orWhereHas('authorshipAllocations', fn ($authors) => $authors
                        ->where('faculty_id', $request->user()->id)
                        ->whereHas('submission', fn ($submission) => $submission->whereNot('status', SubmissionStatus::Draft)));
            })
            ->with(['academicYear:id,name', 'researchType:slug,name', 'evaluation', 'approval', 'authorshipAllocations.faculty:id,name,email'])
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', $search)
                        ->orWhere('reference_code', 'like', $search);
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Faculty/Submissions/Index', [
            'submissions' => $submissions,
            'filters' => $request->only(['status', 'search']),
            'statuses' => collect(SubmissionStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ResearchSubmission::class);

        return Inertia::render('Faculty/Submissions/Create', $this->formMeta($request->user()));
    }

    public function extractMetadata(
        ExtractResearchMetadataRequest $request,
        ResearchPaperMetadataExtractor $extractor,
    ): JsonResponse {
        try {
            return response()->json($extractor->extract($request->file('paper')));
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function downloadAuthorization(Request $request, ResearchSubmissionAuthor $author): StreamedResponse
    {
        $this->authorizeProofAccess($request, $author);

        return Storage::disk($author->authorization_disk ?? 'local')->download(
            $author->authorization_path,
            $author->authorization_original_name ?? 'coauthor-authorization',
        );
    }

    public function viewAuthorization(Request $request, ResearchSubmissionAuthor $author): StreamedResponse
    {
        $this->authorizeProofAccess($request, $author);
        abort_if($author->authorization_kind === 'file', 404);

        $extension = strtolower(pathinfo((string) $author->authorization_original_name, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        return Storage::disk($author->authorization_disk ?? 'local')->response(
            $author->authorization_path,
            $author->authorization_original_name,
            ['Content-Type' => $contentType, 'X-Content-Type-Options' => 'nosniff'],
            'inline',
        );
    }

    private function authorizeProofAccess(Request $request, ResearchSubmissionAuthor $author): void
    {
        $submission = $author->submission;
        $viewer = $request->user();
        $mayViewAuthorization = ($viewer->is_active && $viewer->isAdmin())
            || ($viewer->isCoordinator()
                && $viewer->is_active
                && $viewer->department_id !== null
                && $viewer->department_id === $submission->department_id)
            || ($viewer->is_active && $viewer->id === $submission->faculty_id);

        abort_unless($mayViewAuthorization && $author->authorization_path, 403);
    }

    public function store(StoreResearchSubmissionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if (($data['activity_dates']['mode'] ?? null) === 'multiple') {
            sort($data['activity_dates']['dates']);
        }

        $claim = app(ResearchClaimCalculator::class)->calculate($request, ResearchType::query()->where('slug', $data['research_type'])->firstOrFail());
        $points = $claim['claimed_points'];

        $storedFiles = [];
        $obsoleteFiles = [];
        try {
            $submission = DB::transaction(function () use ($request, $user, $data, $points, $claim, &$storedFiles, &$obsoleteFiles) {
                $submission = ResearchSubmission::query()->create([
                    ...collect($data)->except(['attachments', 'attachment_requirements', 'submit_now', 'author_details', 'claimed_points', 'sdgs', 'research_agendas'])->all(),
                    'faculty_id' => $user->id,
                    'college_id' => $user->college_id,
                    'department_id' => $user->department_id,
                    'status' => SubmissionStatus::Draft,
                    ...$claim,
                    'normalized_title' => ResearchSubmission::normalizeTitle($data['title']),
                ]);

                $this->authorService->sync($submission, $data['author_details'], $points, $storedFiles, $obsoleteFiles);
                $submission->sdgs()->sync($data['sdgs'] ?? []);
                $submission->researchAgendas()->sync($data['research_agendas'] ?? []);
                $this->storeAttachments($request, $submission, $storedFiles);

                if ($request->boolean('submit_now')) {
                    $this->workflow->submit($submission, $user, 'Submitted upon creation.');
                }

                return $submission;
            });
        } catch (\Throwable $exception) {
            $this->deleteFiles($storedFiles);
            $this->throwDuplicateValidationError($exception, $data, $user);
            throw $exception;
        }
        $this->deleteFiles($obsoleteFiles);

        return redirect()
            ->route('faculty.submissions.show', $submission)
            ->with('success', $request->boolean('submit_now')
                ? 'Research involvement submitted for evaluation.'
                : 'Draft saved successfully.');
    }

    public function show(ResearchSubmission $submission): Response
    {
        $this->authorize('viewPersonal', $submission);

        $submission->load([
            'faculty:id,name,email',
            'academicYear:id,name',
            'researchType:slug,name,has_authors,semester_ceiling',
            'college:id,name',
            'department:id,name',
            'attachments',
            'evaluation.coordinator:id,name',
            'approval.admin:id,name',
            'statusHistories.user:id,name',
            'authorshipAllocations.faculty:id,name,email,designation,department_id',
            'sdgs:id,number,name,image_path',
            'researchAgendas:id,name,image_path',
        ]);

        return Inertia::render('Faculty/Submissions/Show', [
            'submission' => $submission,
            'can' => [
                'update' => request()->user()->can('update', $submission),
                'submit' => request()->user()->can('submit', $submission),
                'delete' => request()->user()->can('delete', $submission),
                'viewCertificate' => request()->user()->can('viewCertificate', $submission),
                'viewAuthorizations' => request()->user()->isAdmin()
                    || (request()->user()->isCoordinator()
                        && request()->user()->department_id !== null
                        && request()->user()->department_id === $submission->department_id)
                    || (request()->user()->is_active && request()->user()->id === $submission->faculty_id),
            ],
        ]);
    }

    public function edit(Request $request, ResearchSubmission $submission): Response
    {
        $this->authorize('update', $submission);

        $submission->load(['attachments', 'authorshipAllocations.faculty:id,name,email', 'sdgs:id,number,name,image_path', 'researchAgendas:id,name,image_path']);

        $meta = $this->formMeta($request->user());
        if ($submission->unit_snapshot !== null) {
            foreach ($meta['researchTypes'] as &$type) {
                if ($type['value'] === $submission->research_type) {
                    $type['unit'] = $submission->unit_snapshot;
                }
            }
            unset($type);
            $meta['researchTypePoints']->put($submission->research_type, $submission->rate_snapshot);
        }

        return Inertia::render('Faculty/Submissions/Edit', [
            ...$meta,
            'submission' => $submission,
        ]);
    }

    public function update(UpdateResearchSubmissionRequest $request, ResearchSubmission $submission): RedirectResponse
    {
        $data = $request->validated();
        if (($data['activity_dates']['mode'] ?? null) === 'multiple') {
            sort($data['activity_dates']['dates']);
        }

        if (array_key_exists('funding_source', $data) && ! array_key_exists('funding_organization', $data)) {
            $data['funding_organization'] = null;
        }

        $claim = app(ResearchClaimCalculator::class)->calculate($request, ResearchType::query()->where('slug', $data['research_type'])->firstOrFail());
        $points = $claim['claimed_points'];

        $storedFiles = [];
        $obsoleteFiles = [];
        try {
            DB::transaction(function () use ($request, $submission, $data, $points, $claim, &$storedFiles, &$obsoleteFiles) {
                $existingMappings = $data['existing_attachment_requirements'] ?? [];
                $attachmentIds = $submission->attachments()->pluck('id')->map(fn ($id) => (string) $id)->all();
                if (array_diff(array_keys($existingMappings), $attachmentIds)) {
                    throw ValidationException::withMessages(['evidence' => 'An attachment does not belong to this submission.']);
                }
                if ($submission->research_type !== $data['research_type']) {
                    $submission->attachments()->update(['research_evidence_requirement_id' => null]);
                }
                foreach ($existingMappings as $attachmentId => $requirementId) {
                    $submission->attachments()->whereKey($attachmentId)->update(['research_evidence_requirement_id' => $requirementId ?: null]);
                }
                $submission->update([
                    ...collect($data)->except(['attachments', 'attachment_requirements', 'existing_attachment_requirements', 'submit_now', 'author_details', 'claimed_points', 'sdgs', 'research_agendas'])->all(),
                    ...$claim,
                    'normalized_title' => ResearchSubmission::normalizeTitle($data['title']),
                ]);
                $this->authorService->sync($submission, $data['author_details'], $points, $storedFiles, $obsoleteFiles);
                $submission->sdgs()->sync($data['sdgs'] ?? []);
                $submission->researchAgendas()->sync($data['research_agendas'] ?? []);
                $this->storeAttachments($request, $submission, $storedFiles);

                if ($request->boolean('submit_now')) {
                    $this->workflow->submit($submission->fresh(), $request->user(), 'Submitted after revision.');
                }
            });
        } catch (\Throwable $exception) {
            $this->deleteFiles($storedFiles);
            $this->throwDuplicateValidationError($exception, $data, $request->user(), $submission->id);
            throw $exception;
        }
        $this->deleteFiles($obsoleteFiles);

        return redirect()
            ->route('faculty.submissions.show', $submission)
            ->with('success', $request->boolean('submit_now')
                ? 'Submission sent for evaluation.'
                : 'Submission updated successfully.');
    }

    private function throwDuplicateValidationError(\Throwable $exception, array $data, User $user, ?int $exceptId = null): void
    {
        $duplicates = app(ResearchSubmissionDuplicateService::class);

        if (! $exception instanceof UniqueConstraintViolationException || ! $duplicates->isTitleTypeViolation($exception)) {
            return;
        }

        $message = $duplicates->message($data['title'], $data['research_type'], $user, $exceptId);
        if ($message !== null) {
            throw ValidationException::withMessages(['title' => $message]);
        }
    }

    public function submit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        $this->authorize('submit', $submission);

        $this->workflow->submit($submission, $request->user());

        return back()->with('success', 'Research involvement submitted for coordinator evaluation.');
    }

    public function destroy(ResearchSubmission $submission): RedirectResponse
    {
        $this->authorize('delete', $submission);

        foreach ($submission->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        }

        foreach ($submission->authorshipAllocations as $author) {
            if ($author->authorization_path) {
                Storage::disk($author->authorization_disk ?? 'local')->delete($author->authorization_path);
            }
        }

        $submission->delete();

        return redirect()
            ->route('faculty.submissions.index')
            ->with('success', 'Draft submission deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formMeta(User $user): array
    {
        return [
            'uploadLimits' => [
                'file' => $this->iniBytes('upload_max_filesize'),
                'total' => $this->iniBytes('post_max_size'),
            ],
            'academicYears' => AcademicYear::query()->active()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'researchTypes' => ResearchType::active()->orderBy('sort_order')->orderBy('name')
                ->with(['evidenceRequirements' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
                ->get(['id', 'slug', 'name', 'category', 'has_authors', 'unit', 'semester_ceiling'])
                ->map(fn (ResearchType $type): array => ['value' => $type->slug, 'label' => $type->name, 'group' => $type->category ?? 'Other', 'has_authors' => $type->has_authors, 'unit' => $type->unit, 'semester_ceiling' => $type->semester_ceiling, 'requirements' => $type->evidenceRequirements])
                ->all(),
            'researchTypePoints' => ResearchTypePointSetting::query()->pluck('points_per_output', 'research_type'),
            'sdgs' => Sdg::query()->where('is_active', true)->orderBy('number')->get(['id', 'number', 'name', 'image_path']),
            'researchAgendas' => ResearchAgenda::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'description', 'image_path']),
            'currentFaculty' => $user->only(['id', 'name', 'email', 'institutional_profile_url', 'research_interests']),
            'facultyDirectory' => User::query()
                ->whereIn('role', [UserRole::Faculty, UserRole::Coordinator, UserRole::Admin])
                ->where('is_active', true)
                ->with(['college:id,name', 'department:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'college_id', 'department_id']),
        ];
    }

    private function iniBytes(string $setting): int
    {
        $value = trim((string) ini_get($setting));
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function storeAttachments(Request $request, ResearchSubmission $submission, array &$storedFiles): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $index => $file) {
            $path = $file->store('research-attachments/'.$submission->uuid, 'local');
            $storedFiles[] = ['local', $path];

            SubmissionAttachment::query()->create([
                'research_submission_id' => $submission->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'category' => 'evidence',
                'uploaded_by' => $request->user()->id,
                'research_evidence_requirement_id' => $request->input("attachment_requirements.$index") ?: null,
            ]);
        }
    }

    private function deleteFiles(array $files): void
    {
        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
