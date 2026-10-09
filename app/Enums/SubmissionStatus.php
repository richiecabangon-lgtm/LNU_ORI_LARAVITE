<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderEvaluation = 'under_evaluation';
    case Evaluated = 'evaluated';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderEvaluation => 'Under Evaluation',
            self::Evaluated => 'Evaluated',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::RevisionRequested => 'Revision Requested',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'blue',
            self::UnderEvaluation => 'indigo',
            self::Evaluated => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::RevisionRequested => 'orange',
        };
    }

    public function isEditableByFaculty(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequested], true);
    }

    public function canBeEvaluated(): bool
    {
        return in_array($this, [self::Submitted, self::UnderEvaluation], true);
    }

    public function canBeApproved(): bool
    {
        return $this === self::Evaluated;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
