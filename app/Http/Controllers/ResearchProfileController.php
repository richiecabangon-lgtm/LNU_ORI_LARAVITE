<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ResearchAgenda;
use App\Models\ResearchSubmission;
use App\Models\Sdg;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResearchProfileController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();

        if ($viewer->isFaculty()) {
            return redirect()->route('research-profiles.show', $viewer);
        }

        abort_unless($viewer->isCoordinator() || $viewer->isAdmin(), 403);

        $facultyQuery = User::query()
            ->where('role', UserRole::Faculty)
            ->where('is_active', true)
            ->with(['college:id,name', 'department:id,name'])
            ->withCount(['submissions' => fn ($query) => $query->whereNot('status', 'draft')])
            ->withCount(['researchAuthorships as coauthored_submissions_count' => function ($query): void {
                $query->whereHas('submission', fn ($submission) => $submission->whereColumn(
                    'research_submissions.faculty_id',
                    '!=',
                    'research_submission_authors.faculty_id',
                )->whereNot('status', 'draft'));
            }]);

        if ($viewer->isCoordinator()) {
            $facultyQuery->where('department_id', $viewer->department_id ?? 0);
        }

        $faculty = $facultyQuery
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('employee_id', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $faculty->getCollection()->each(function (User $person): void {
            $person->makeHidden(['email']);
        });

        return Inertia::render('ResearchProfiles/Index', [
            'faculty' => $faculty,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        abort_unless($user->isFaculty(), 404);
        abort_unless(! $request->user()->isFaculty() || $request->user()->is($user), 403);
        abort_unless(! $request->user()->isCoordinator()
            || ($request->user()->department_id !== null && $request->user()->department_id === $user->department_id), 404);

        $submissions = ResearchSubmission::query()
            ->where(function ($query) use ($user) {
                $query->where('faculty_id', $user->id)
                    ->orWhereHas('authorshipAllocations', fn ($authors) => $authors->where('faculty_id', $user->id));
            })
            ->with(['college:id,name', 'department:id,name', 'researchType:slug,name', 'sdgs:id,number,name,image_path', 'researchAgendas:id,name,image_path', 'evaluation', 'approval', 'authorshipAllocations'])
            ->whereNot('status', 'draft')
            ->latest('publication_date')
            ->get()
            ->map(function (ResearchSubmission $submission) use ($user): array {
                $facultyPoints = $submission->pointsForFaculty($user);

                return [
                    'id' => $submission->id,
                    'uuid' => $submission->uuid,
                    'reference_code' => $submission->reference_code,
                    'title' => $submission->title,
                    'research_type' => $submission->research_type_label,
                    'abstract' => $submission->abstract,
                    'venue' => $submission->venue,
                    'doi' => $submission->doi,
                    'publication_date' => $submission->publication_date?->toDateString(),
                    'status' => $submission->status->value,
                    'faculty_points' => $facultyPoints,
                    'is_submitter' => $submission->faculty_id === $user->id,
                    'college' => $submission->college?->name,
                    'department' => $submission->department?->name,
                    'sdgs' => $submission->sdgs->map(fn (Sdg $sdg): array => ['number' => $sdg->number, 'name' => $sdg->name, 'image_url' => $sdg->image_url])->all(),
                    'research_agendas' => $submission->researchAgendas->map(fn (ResearchAgenda $agenda): array => ['name' => $agenda->name, 'image_url' => $agenda->image_url])->all(),
                ];
            });

        $facultyData = $user->only(['id', 'name', 'employee_id', 'designation', 'academic_rank', 'orcid_url', 'google_scholar_url', 'institutional_profile_url']);
        $facultyData['profile_photo_url'] = $user->profile_photo_url;
        $canSeeContact = $request->user()->is($user) || $request->user()->isAdmin();

        if ($canSeeContact) {
            $facultyData['email'] = $user->email;
        }

        return Inertia::render('ResearchProfiles/Show', [
            'faculty' => $facultyData,
            'college' => $user->college()->value('name'),
            'department' => $user->department()->value('name'),
            'submissions' => $submissions,
            'canSeeContact' => $canSeeContact,
        ]);
    }
}
