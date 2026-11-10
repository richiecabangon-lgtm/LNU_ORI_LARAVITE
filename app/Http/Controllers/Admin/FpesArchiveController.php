<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FpesArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FpesArchiveController extends Controller
{
    private function dates(Request $request): array
    {
        return $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $dates = $this->dates($request);
        FpesArchiveService::ensureNoRangeOverlap($dates);
        $ids = FpesArchiveService::eligible($dates)->orderBy('id')->pluck('id')->all();
        FpesArchiveService::ensureNoMembershipOverlap($ids);

        return response()->json([...$dates, 'ids' => $ids, 'count' => count($ids)]);
    }

    public function store(Request $request): JsonResponse
    {
        $dates = $this->dates($request);
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $dates, $validated): void {
            FpesArchiveService::lock();
            FpesArchiveService::ensureNoRangeOverlap($dates);
            $ids = FpesArchiveService::eligible($dates)->orderBy('id')->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $confirmedIds = array_map('intval', $validated['ids']);
            sort($confirmedIds);
            if ($ids !== $confirmedIds) {
                throw ValidationException::withMessages(['ids' => 'The eligible submissions changed. Preview the range again.']);
            }
            FpesArchiveService::ensureNoMembershipOverlap($ids);

            $archiveId = DB::table('fpes_archives')->insertGetId([
                ...$dates,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('fpes_archive_submissions')->insert(array_map(fn ($id) => [
                    'fpes_archive_id' => $archiveId,
                    'research_submission_id' => $id,
                ], $chunk));
            }
        }, 3);

        return response()->json(['message' => 'Archive created.']);
    }

    public function restore(Request $request, int $archive): JsonResponse
    {
        DB::transaction(function () use ($request, $archive): void {
            FpesArchiveService::lock();
            $record = DB::table('fpes_archives')->where('id', $archive)->lockForUpdate()->first();
            abort_if(! $record, 404);
            if ($record->restored_at !== null) {
                throw ValidationException::withMessages(['archive' => 'This archive has already been restored.']);
            }
            DB::table('fpes_archives')->where('id', $archive)->update([
                'restored_at' => now(),
                'restored_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['message' => 'Archive restored.']);
    }
}
