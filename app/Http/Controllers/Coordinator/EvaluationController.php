<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\EvaluationRecommendation;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvaluationRequest;
use App\Models\ResearchSubmission;
use App\Services\ResearchWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function __construct(private readonly ResearchWorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ResearchSubmission::class);

        $queue = ResearchSubmission::query()
            ->with(['faculty:id,name', 'academicYear:id,name', 'researchType:slug,name', 'college:id,name'])
            ->where('department_id', $request->user()->department_id ?? 0)
            ->when(
                $request->string('tab')->toString() === 'completed',
                fn ($q) => $q->whereIn('status', [
                    SubmissionStatus::Evaluated,
                    SubmissionStatus::Approved,
                    SubmissionStatus::Rejected,
                    SubmissionStatus::RevisionRequested,
                ]),
                fn ($q) => $q->whereIn('status', [
                    SubmissionStatus::Submitted,
                    SubmissionStatus::UnderEvaluation,
                ]),
            )
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', $search)
                        ->orWhere('reference_code', 'like', $search);
                });
            })
            ->latest('submitted_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Coordinator/Evaluations/Index', [
            'submissions' => $queue,
            'filters' => [
                'tab' => $request->string('tab')->toString() ?: 'pending',
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function show(ResearchSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        if ($submission->status === SubmissionStatus::Submitted) {
            $this->workflow->startEvaluation($submission, request()->user());
            $submission->refresh();
        }

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

        return Inertia::render('Coordinator/Evaluations/Show', [
            'submission' => $submission,
            'recommendations' => EvaluationRecommendation::options(),
            'canEvaluate' => request()->user()->can('evaluate', $submission),
        ]);
    }

    public function store(StoreEvaluationRequest $request, ResearchSubmission $submission): RedirectResponse
    {
        $this->workflow->evaluate($submission, $request->user(), $request->validated());

        return redirect()
            ->route('coordinator.evaluations.index')
            ->with('success', 'Evaluation recorded successfully.');
    }
}
