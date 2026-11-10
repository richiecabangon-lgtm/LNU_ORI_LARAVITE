<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FpesArchiveService
{
    public const REPORT_DATE_SQL = 'COALESCE(publication_date, DATE(approved_at), DATE(submitted_at), DATE(created_at))';

    public static function eligible(array $dates): Builder
    {
        return ResearchSubmission::query()->where('status', SubmissionStatus::Approved)
            ->whereRaw(self::REPORT_DATE_SQL.' >= ? and '.self::REPORT_DATE_SQL.' < ?', [
                $dates['start_date'], CarbonImmutable::parse($dates['end_date'])->addDay()->toDateString(),
            ]);
    }

    public static function lock(): void
    {
        DB::table('fpes_archive_locks')->where('id', 1)->increment('version');
    }

    public static function ensureNoRangeOverlap(array $dates): void
    {
        if (DB::table('fpes_archives')->whereNull('restored_at')
            ->where('start_date', '<=', $dates['end_date'])->where('end_date', '>=', $dates['start_date'])->exists()) {
            throw ValidationException::withMessages(['start_date' => 'This range overlaps an active archive.']);
        }
    }

    public static function ensureNoMembershipOverlap(array $ids): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            if (DB::table('fpes_archive_submissions')->join('fpes_archives', 'fpes_archives.id', '=', 'fpes_archive_submissions.fpes_archive_id')
                ->whereNull('fpes_archives.restored_at')->whereIn('fpes_archive_submissions.research_submission_id', $chunk)->exists()) {
                throw ValidationException::withMessages(['ids' => 'A submission is already in an active archive.']);
            }
        }
    }
}
