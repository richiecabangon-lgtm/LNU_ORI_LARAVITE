<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\CertificationSetting;
use App\Models\ResearchSubmission;
use App\Models\User;

class ResearchSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ResearchSubmission $submission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->is_active && ($submission->faculty_id === $user->id
            || ($submission->status !== SubmissionStatus::Draft
                && $submission->authorshipAllocations()->where('faculty_id', $user->id)->exists()))) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCoordinator()) {
            return $user->is_active
                && $user->department_id !== null
                && $submission->department_id === $user->department_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole([UserRole::Faculty, UserRole::Coordinator, UserRole::Admin]);
    }

    public function viewPersonal(User $user, ResearchSubmission $submission): bool
    {
        return $user->is_active && ($submission->faculty_id === $user->id
            || ($submission->status !== SubmissionStatus::Draft && $this->isParticipant($user, $submission)));
    }

    public function review(User $user, ResearchSubmission $submission): bool
    {
        return $user->is_active && ! $submission->hasParticipant($user);
    }

    public function viewCertificate(User $user, ResearchSubmission $submission): bool
    {
        return $user->is_active
            && $submission->status === SubmissionStatus::Approved
            && $this->isParticipant($user, $submission)
            && ($submission->research_type === 'published_research_published_individual_research_scopus_indexed_journal'
                || (CertificationSetting::query()->find(1)?->allow_non_scopus ?? false));
    }

    public function update(User $user, ResearchSubmission $submission): bool
    {
        return $user->is_active
            && $user->hasRole([UserRole::Faculty, UserRole::Coordinator, UserRole::Admin])
            && $submission->faculty_id === $user->id
            && $submission->status->isEditableByFaculty();
    }

    public function delete(User $user, ResearchSubmission $submission): bool
    {
        return $user->is_active
            && $user->hasRole([UserRole::Faculty, UserRole::Coordinator, UserRole::Admin])
            && $submission->faculty_id === $user->id
            && $submission->status === SubmissionStatus::Draft;
    }

    public function submit(User $user, ResearchSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    public function evaluate(User $user, ResearchSubmission $submission): bool
    {
        return $user->isCoordinator()
            && $user->is_active
            && $user->department_id !== null
            && $submission->department_id === $user->department_id
            && ! $submission->hasParticipant($user)
            && $submission->status->canBeEvaluated();
    }

    public function approve(User $user, ResearchSubmission $submission): bool
    {
        return $user->isAdmin()
            && $user->is_active
            && ! $submission->hasParticipant($user)
            && $submission->status->canBeApproved();
    }

    private function isParticipant(User $user, ResearchSubmission $submission): bool
    {
        return $submission->faculty_id === $user->id
            || $submission->authorshipAllocations()->where('faculty_id', $user->id)->exists();
    }

    public function manageUsers(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
