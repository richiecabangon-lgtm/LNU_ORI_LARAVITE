<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApprovalRequest;
use App\Models\ResearchSubmission;
use App\Services\ResearchWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private readonly ResearchWorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ResearchSubmission::class);

        $submissions = ResearchSubmission::query()
            ->with([
                'faculty:id,name',
                'academicYear:id,name',
                'researchType:slug,name',
                'evaluation.coordinator:id,name',
                'approval',
            ])
            ->when(
                $request->string('tab')->toString() === 'history',
                fn ($q) => $q->whereIn('status', [
                    SubmissionStatus::Approved,
                    SubmissionStatus::Rejected,
                ]),
                fn ($q) => $q->where('status', SubmissionStatus::Evaluated),
            )
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', $search)
                        ->orWhere('reference_code', 'like', $search);
                });
            })
            ->latest('evaluated_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Approvals/Index', [
            'submissions' => $submissions,
            'filters' => [
                'tab' => $request->string('tab')->toString() ?: 'pending',
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function show(ResearchSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        $submission->load([
            'faculty:id,name,email,designation',
            'academicYear:id,name',
            'researchType:slug,name,has_authors',
            'college:id,name',
            'department:id,name',
            'attachments',
            'evaluation.coordinator:id,name',
            'approval.admin:id,name',
            'statusHistories.user:id,name',
            'authorshipAllocations.faculty:id,name,email',
            'sdgs:id,number,name,image_path',
            'researchAgendas:id,name,image_path',
        ]);

        return Inertia::render('Admin/Approvals/Show', [
            'submission' => $submission,
            'canApprove' => request()->user()->can('approve', $submission),
        ]);
    }

    public function store(StoreApprovalRequest $request, ResearchSubmission $submission): RedirectResponse
    {
        $this->workflow->decide($submission, $request->user(), $request->validated());

        $message = $request->string('decision')->toString() === 'approved'
            ? 'Research involvement approved.'
            : 'Research involvement rejected.';

        return redirect()
            ->route('admin.approvals.index')
            ->with('success', $message);
    }
}
