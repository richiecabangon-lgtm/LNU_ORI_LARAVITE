<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class ResearchSubmissionDuplicateService
{
    public function isTitleTypeViolation(UniqueConstraintViolationException $exception): bool
    {
        $message = $exception->getPrevious()?->getMessage() ?? '';

        return preg_match('/(?<![a-zA-Z0-9_])research_submission_type_hash_unique(?![a-zA-Z0-9_])/', $message) === 1
            || preg_match('/UNIQUE constraint failed: research_submissions\.normalized_title_hash, research_submissions\.research_type(?:\r?\n|$)/', $message) === 1;
    }

    public function message(string $title, string $type, User $user, ?int $exceptId = null): ?string
    {
        $duplicate = ResearchSubmission::withTrashed()
            ->useWritePdo()
            ->where('normalized_title_hash', hash('sha256', ResearchSubmission::normalizeTitle($title)))
            ->where('research_type', $type)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->first();

        if (! $duplicate) {
            return null;
        }

        if (! $duplicate->trashed()
            && $duplicate->status !== SubmissionStatus::Draft
            && $duplicate->faculty_id !== $user->id
            && $duplicate->authorshipAllocations()->where('faculty_id', $user->id)->exists()) {
            return 'This title and involvement type has already been submitted by a co-author.';
        }

        return 'An involvement with this title and type already exists.';
    }
}
