<?php

namespace App\Services;

use App\Enums\EvaluationRecommendation;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Approval;
use App\Models\Evaluation;
use App\Models\ResearchSubmission;
use App\Models\StatusHistory;
use App\Models\User;
use App\Notifications\ResearchWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ResearchWorkflowService
{
    public function submit(ResearchSubmission $submission, User $actor, ?string $notes = null): ResearchSubmission
    {
        $this->assertTransition($submission, [
            SubmissionStatus::Draft,
            SubmissionStatus::RevisionRequested,
        ]);

        if (! $actor->isFaculty() && ! $submission->department_id && ! $actor->department_id) {
            throw ValidationException::withMessages([
                'department' => 'Ask an administrator to assign your department before submitting research for evaluation.',
            ]);
        }

        $updated = DB::transaction(function () use ($submission, $actor, $notes) {
            if (! $submission->department_id && $actor->department_id) {
                $submission->update(['department_id' => $actor->department_id, 'college_id' => $actor->college_id]);
            }
            $type = $submission->researchType()->lockForUpdate()->firstOrFail();
            $requirements = $type->evidenceRequirements()->where('is_active', true)->orderBy('id')->get();
            $attachments = $submission->attachments()->get();
            $missing = $requirements->filter(fn ($requirement) => $requirement->is_required
                && ! $attachments->contains('research_evidence_requirement_id', $requirement->id));

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'evidence' => 'Missing required evidence: '.$missing->pluck('name')->join(', ').'.',
                ]);
            }

            $snapshot = $requirements->map(fn ($requirement) => [
                'id' => $requirement->id,
                'name' => $requirement->name,
                'is_required' => $requirement->is_required,
                'attachments' => $attachments->where('research_evidence_requirement_id', $requirement->id)
                    ->map(fn ($attachment) => ['id' => $attachment->id, 'name' => $attachment->original_name])->values()->all(),
            ])->all();

            return $this->transition(
                submission: $submission,
                actor: $actor,
                to: SubmissionStatus::Submitted,
                action: 'submitted',
                notes: $notes,
                attributes: ['submitted_at' => now()],
                meta: ['evidence_requirements' => $snapshot],
            );
        });

        $updated->loadMissing('authorshipAllocations');
        User::query()->where('role', UserRole::Coordinator)
            ->where('is_active', true)
            ->where('department_id', $submission->department_id)
            ->each(function (User $coordinator) use ($updated, $actor): void {
                if ($coordinator->can('evaluate', $updated)) {
                    $coordinator->notify(new ResearchWorkflowNotification($updated, $actor, 'submitted', $updated->status));
                }
            });

        return $updated;
    }

    public function startEvaluation(ResearchSubmission $submission, User $actor): ResearchSubmission
    {
        $this->assertTransition($submission, [SubmissionStatus::Submitted]);

        return $this->transition(
            submission: $submission,
            actor: $actor,
            to: SubmissionStatus::UnderEvaluation,
            action: 'evaluation_started',
        );
    }

    /**
     * @param  array{
     *     score?: float|null,
     *     quality_rating?: int|null,
     *     relevance_rating?: int|null,
     *     documentation_rating?: int|null,
     *     recommendation: EvaluationRecommendation|string,
     *     awarded_points?: float|null,
     *     remarks: string
     * }  $data
     */
    public function evaluate(ResearchSubmission $submission, User $coordinator, array $data): ResearchSubmission
    {
        $this->assertTransition($submission, [
            SubmissionStatus::Submitted,
            SubmissionStatus::UnderEvaluation,
        ]);

        $recommendation = $data['recommendation'] instanceof EvaluationRecommendation
            ? $data['recommendation']
            : EvaluationRecommendation::from($data['recommendation']);

        return DB::transaction(function () use ($submission, $coordinator, $data, $recommendation) {
            Evaluation::query()->updateOrCreate(
                ['research_submission_id' => $submission->id],
                [
                    'coordinator_id' => $coordinator->id,
                    'score' => $data['score'] ?? null,
                    'quality_rating' => $data['quality_rating'] ?? null,
                    'relevance_rating' => $data['relevance_rating'] ?? null,
                    'documentation_rating' => $data['documentation_rating'] ?? null,
                    'recommendation' => $recommendation,
                    'awarded_points' => $data['awarded_points'] ?? null,
                    'remarks' => $data['remarks'],
                    'evaluated_at' => now(),
                ],
            );

            $nextStatus = match ($recommendation) {
                EvaluationRecommendation::RequestRevision => SubmissionStatus::RevisionRequested,
                EvaluationRecommendation::RecommendRejection => SubmissionStatus::Evaluated,
                EvaluationRecommendation::RecommendApproval => SubmissionStatus::Evaluated,
            };

            $updated = $this->transition(
                submission: $submission->fresh(),
                actor: $coordinator,
                to: $nextStatus,
                action: 'evaluated',
                notes: $data['remarks'],
                attributes: ['evaluated_at' => now()],
                meta: ['recommendation' => $recommendation->value],
            );

            $this->notifySubmissionFaculty($updated, $coordinator, 'evaluated');
            if ($updated->status === SubmissionStatus::Evaluated) {
                $updated->loadMissing('authorshipAllocations');
                User::query()->where('role', UserRole::Admin)->where('is_active', true)
                    ->each(function (User $admin) use ($updated, $coordinator): void {
                        if ($admin->can('approve', $updated)) {
                            $admin->notify(new ResearchWorkflowNotification($updated, $coordinator, 'evaluated', $updated->status));
                        }
                    });
            }

            return $updated;
        });
    }

    /**
     * @param  array{
     *     decision: string,
     *     final_points?: float|null,
     *     remarks?: string|null
     * }  $data
     */
    public function decide(ResearchSubmission $submission, User $admin, array $data): ResearchSubmission
    {
        $this->assertTransition($submission, [SubmissionStatus::Evaluated]);

        $decision = $data['decision'];
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid approval decision.');
        }

        return DB::transaction(function () use ($submission, $admin, $data, $decision) {
            Approval::query()->updateOrCreate(
                ['research_submission_id' => $submission->id],
                [
                    'admin_id' => $admin->id,
                    'decision' => $decision,
                    'final_points' => $data['final_points'] ?? $submission->evaluation?->awarded_points,
                    'remarks' => $data['remarks'] ?? null,
                    'decided_at' => now(),
                ],
            );

            $to = $decision === 'approved'
                ? SubmissionStatus::Approved
                : SubmissionStatus::Rejected;

            $updated = $this->transition(
                submission: $submission->fresh(),
                actor: $admin,
                to: $to,
                action: $decision,
                notes: $data['remarks'] ?? null,
                attributes: $decision === 'approved' ? ['approved_at' => now()] : [],
                meta: ['decision' => $decision],
            );

            $this->notifySubmissionFaculty($updated, $admin, $decision);
            $coordinator = $updated->evaluation?->coordinator;
            if ($coordinator && $coordinator->is_active) {
                $coordinator->notify(new ResearchWorkflowNotification($updated, $admin, $decision, $updated->status));
            }

            return $updated;
        });
    }

    /**
     * @param  list<SubmissionStatus>  $allowed
     */
    private function assertTransition(ResearchSubmission $submission, array $allowed): void
    {
        if (! in_array($submission->status, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition submission from status [{$submission->status->value}]."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $meta
     */
    private function transition(
        ResearchSubmission $submission,
        User $actor,
        SubmissionStatus $to,
        string $action,
        ?string $notes = null,
        array $attributes = [],
        ?array $meta = null,
    ): ResearchSubmission {
        return DB::transaction(function () use ($submission, $actor, $to, $action, $notes, $attributes, $meta) {
            $from = $submission->status;

            $submission->fill(array_merge($attributes, ['status' => $to]));
            $submission->save();

            StatusHistory::query()->create([
                'research_submission_id' => $submission->id,
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'action' => $action,
                'notes' => $notes,
                'meta' => $meta,
            ]);

            return $submission->fresh([
                'faculty',
                'academicYear',
                'college',
                'department',
                'attachments',
                'evaluation.coordinator',
                'approval.admin',
                'statusHistories.user',
            ]);
        });
    }

    private function notifySubmissionFaculty(ResearchSubmission $submission, User $actor, string $action): void
    {
        $facultyIds = $submission->authorshipAllocations()
            ->whereNotNull('faculty_id')
            ->pluck('faculty_id')
            ->push($submission->faculty_id)
            ->unique();

        User::query()->whereIn('id', $facultyIds)->where('is_active', true)->where('id', '!=', $actor->id)
            ->each(fn (User $faculty) => $faculty->notify(new ResearchWorkflowNotification($submission, $actor, $action, $submission->status)));
    }
}
