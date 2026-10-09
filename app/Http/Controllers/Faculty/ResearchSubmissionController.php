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
use App\Services\ResearchPaperMetadataExtractor;
use App\Services\ResearchSubmissionAuthorService;
use App\Services\ResearchWorkflowService;
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
        $mayViewAuthorization = $viewer->isAdmin()
            || ($viewer->isCoordinator()
                && $viewer->is_active
                && $viewer->department_id !== null
                && $viewer->department_id === $submission->department_id)
            || ($viewer->isFaculty() && $viewer->id === $submission->faculty_id);

        abort_unless($mayViewAuthorization && $author->authorization_path, 403);
    }

    public function store(StoreResearchSubmissionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $points = $this->configuredPoints($data['research_type']);

        $submission = DB::transaction(function () use ($request, $user, $data, $points) {
            $submission = ResearchSubmission::query()->create([
                ...collect($data)->except(['attachments', 'submit_now', 'author_details', 'claimed_points', 'sdgs', 'research_agendas'])->all(),
                'faculty_id' => $user->id,
                'college_id' => $user->college_id,
                'department_id' => $user->department_id,
                'status' => SubmissionStatus::Draft,
                'claimed_points' => $points,
                'normalized_title' => ResearchSubmission::normalizeTitle($data['title']),
            ]);

            $this->authorService->sync($submission, $data['author_details'], $points);
            $submission->sdgs()->sync($data['sdgs'] ?? []);
            $submission->researchAgendas()->sync($data['research_agendas'] ?? []);
            $this->storeAttachments($request, $submission);

            if ($request->boolean('submit_now')) {
                $this->workflow->submit($submission, $user, 'Submitted upon creation.');
            }

            return $submission;
        });

        return redirect()
            ->route('faculty.submissions.show', $submission)
            ->with('success', $request->boolean('submit_now')
                ? 'Research involvement submitted for evaluation.'
                : 'Draft saved successfully.');
    }

    public function show(ResearchSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        $submission->load([
            'faculty:id,name,email',
            'academicYear:id,name',
            'researchType:slug,name,has_authors',
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
                'viewAuthorizations' => request()->user()->isAdmin()
                    || (request()->user()->isCoordinator()
                        && request()->user()->department_id !== null
                        && request()->user()->department_id === $submission->department_id)
                    || (request()->user()->isFaculty() && request()->user()->id === $submission->faculty_id),
            ],
        ]);
    }

    public function edit(Request $request, ResearchSubmission $submission): Response
    {
        $this->authorize('update', $submission);

        $submission->load(['attachments', 'researchType:slug,name', 'authorshipAllocations.faculty:id,name,email', 'sdgs:id,number,name,image_path', 'researchAgendas:id,name,image_path']);

        return Inertia::render('Faculty/Submissions/Edit', [
            ...$this->formMeta($request->user()),
            'submission' => $submission,
        ]);
    }

    public function update(UpdateResearchSubmissionRequest $request, ResearchSubmission $submission): RedirectResponse
    {
        $data = $request->validated();

        $points = $this->configuredPoints($data['research_type']);

        DB::transaction(function () use ($request, $submission, $data, $points) {
            $submission->update([
                ...collect($data)->except(['attachments', 'submit_now', 'author_details', 'claimed_points', 'sdgs', 'research_agendas'])->all(),
                'claimed_points' => $points,
                'normalized_title' => ResearchSubmission::normalizeTitle($data['title']),
            ]);
            $this->authorService->sync($submission, $data['author_details'], $points);
            $submission->sdgs()->sync($data['sdgs'] ?? []);
            $submission->researchAgendas()->sync($data['research_agendas'] ?? []);
            $this->storeAttachments($request, $submission);

            if ($request->boolean('submit_now')) {
                $this->workflow->submit($submission->fresh(), $request->user(), 'Submitted after revision.');
            }
        });

        return redirect()
            ->route('faculty.submissions.show', $submission)
            ->with('success', $request->boolean('submit_now')
                ? 'Submission sent for evaluation.'
                : 'Submission updated successfully.');
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
                ->get(['slug', 'name', 'category', 'has_authors'])
                ->map(fn (ResearchType $type): array => ['value' => $type->slug, 'label' => $type->name, 'group' => $type->category ?? 'Other', 'has_authors' => $type->has_authors])
                ->all(),
            'researchTypePoints' => ResearchTypePointSetting::query()->pluck('points_per_output', 'research_type'),
            'sdgs' => Sdg::query()->where('is_active', true)->orderBy('number')->get(['id', 'number', 'name', 'image_path']),
            'researchAgendas' => ResearchAgenda::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'description', 'image_path']),
            'currentFaculty' => $user->only(['id', 'name', 'email', 'institutional_profile_url']),
            'facultyDirectory' => User::query()
                ->where('role', UserRole::Faculty)
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

    private function configuredPoints(string $researchType): float
    {
        $setting = ResearchTypePointSetting::query()->where('research_type', $researchType)->first();

        if (! $setting) {
            throw ValidationException::withMessages([
                'research_type' => 'The administrator has not configured points for this research type yet.',
            ]);
        }

        return (float) $setting->points_per_output;
    }

    private function storeAttachments(Request $request, ResearchSubmission $submission): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $path = $file->store('research-attachments/'.$submission->uuid, 'public');

            SubmissionAttachment::query()->create([
                'research_submission_id' => $submission->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'category' => 'evidence',
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }
}
