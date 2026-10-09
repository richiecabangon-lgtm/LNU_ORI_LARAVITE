<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\ResearchAchievementTierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ResearchAchievementTierService $achievementTiers) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $stats = match ($user->role) {
            UserRole::Faculty => $this->facultyStats($user),
            UserRole::Coordinator => $this->coordinatorStats($user),
            UserRole::Admin => $this->adminStats(),
        };

        $leaderboard = $user->isFaculty() || $user->isCoordinator() || $user->isAdmin()
            ? $this->leaderboard($request, $user)
            : null;

        $recent = match ($user->role) {
            UserRole::Faculty => ResearchSubmission::query()
                ->where(function ($query) use ($user) {
                    $query->where('faculty_id', $user->id)
                        ->orWhereHas('authorshipAllocations', fn ($authors) => $authors
                            ->where('faculty_id', $user->id)
                            ->whereHas('submission', fn ($submission) => $submission->whereNot('status', SubmissionStatus::Draft)));
                })
                ->with('researchType:slug,name')
                ->latest()
                ->limit(5)
                ->get(),
            UserRole::Coordinator => ResearchSubmission::query()
                ->with(['faculty:id,name', 'researchType:slug,name'])
                ->where('department_id', $user->department_id ?? 0)
                ->whereIn('status', [
                    SubmissionStatus::Submitted,
                    SubmissionStatus::UnderEvaluation,
                ])
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            UserRole::Admin => StatusHistory::query()
                ->with(['submission.faculty:id,name', 'user:id,name'])
                ->whereIn('action', ['submitted', 'evaluation_started', 'evaluated', 'approved', 'rejected'])
                ->whereHas('submission')
                ->latest()
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (StatusHistory $activity): array => [
                    'id' => $activity->id,
                    'title' => $activity->submission->title,
                    'reference_code' => $activity->submission->reference_code,
                    'faculty' => ['name' => $activity->submission->faculty?->name],
                    'status' => $activity->to_status->value,
                    'uuid' => $activity->submission->uuid,
                    'action' => $activity->action,
                    'action_label' => $activity->to_status === SubmissionStatus::RevisionRequested
                        ? 'requested revision'
                        : str($activity->action)->replace('_', ' ')->toString(),
                    'actor' => $activity->user?->name,
                    'activity_at' => $activity->created_at?->toIso8601String(),
                ]),
        };

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'analytics' => $this->analytics($user),
            'leaderboard' => $leaderboard,
            'role' => $user->role->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function leaderboard(Request $request, User $viewer): array
    {
        $filters = $viewer->isFaculty() ? [] : $request->validate([
            'leaderboard_scope' => ['sometimes', 'in:college,institution'],
            'leaderboard_college_id' => ['nullable', 'integer', 'exists:colleges,id'],
        ]);
        $scope = $viewer->isFaculty()
            ? 'self'
            : ($filters['leaderboard_scope'] ?? ($viewer->isCoordinator() ? 'college' : 'institution'));
        $collegeId = $viewer->isFaculty() || $viewer->isCoordinator()
            ? ($viewer->college_id ?? Department::query()->whereKey($viewer->department_id)->value('college_id'))
            : ($filters['leaderboard_college_id'] ?? null);

        if ($viewer->isCoordinator() && $scope === 'college' && $collegeId === null) {
            $scope = 'institution';
        }

        $approvedSubmissions = ResearchSubmission::query()
            ->where('status', SubmissionStatus::Approved)
            ->whereHas('approval', fn (Builder $query) => $query->where('decision', 'approved'))
            ->with([
                'faculty:id,name,role,is_active,academic_rank,designation,college_id,department_id',
                'faculty.college:id,name',
                'faculty.department:id,name',
                'approval:id,research_submission_id,decision,final_points',
                'evaluation:id,research_submission_id,awarded_points',
                'authorshipAllocations:id,research_submission_id,faculty_id,allocated_points',
                'authorshipAllocations.faculty:id,name,role,is_active,academic_rank,designation,college_id,department_id',
                'authorshipAllocations.faculty.college:id,name',
                'authorshipAllocations.faculty.department:id,name',
            ])
            ->get();

        $totals = [];
        $activeFaculty = User::query()
            ->where('role', UserRole::Faculty)
            ->where('is_active', true)
            ->with(['college:id,name', 'department:id,name'])
            ->get(['id', 'name', 'role', 'is_active', 'academic_rank', 'designation', 'college_id', 'department_id']);

        foreach ($activeFaculty as $faculty) {
            $this->addLeaderboardPoints($totals, $faculty, $faculty->college_id, 0);
        }

        foreach ($approvedSubmissions as $submission) {
            $approvedPoints = (float) ($submission->approval?->final_points
                ?? $submission->evaluation?->awarded_points
                ?? $submission->claimed_points
                ?? 0);
            $allocations = $submission->authorshipAllocations;

            if ($allocations->isEmpty()) {
                $faculty = $submission->faculty;
                if ($faculty?->isFaculty() && $faculty->is_active) {
                    $this->addLeaderboardPoints($totals, $faculty, $submission->college_id, $approvedPoints);
                }

                continue;
            }

            $allocationTotal = (float) $allocations->sum('allocated_points');
            if ($allocationTotal <= 0) {
                continue;
            }

            foreach ($allocations as $allocation) {
                $faculty = $allocation->faculty;
                if (! $faculty?->isFaculty() || ! $faculty->is_active) {
                    continue;
                }

                $share = $approvedPoints * ((float) $allocation->allocated_points / $allocationTotal);
                $this->addLeaderboardPoints($totals, $faculty, $submission->college_id, $share);
            }
        }

        $collegeFacultyCounts = $activeFaculty
            ->filter(fn (User $faculty): bool => $faculty->college_id !== null)
            ->countBy('college_id');
        $allRankedFaculty = collect($totals)
            ->filter(fn (array $record): bool => $record['points'] > 0)
            ->sort(fn (array $left, array $right): int => ($right['points'] <=> $left['points']) ?: strcasecmp($left['name'], $right['name']))
            ->map(fn (array $record): array => [
                ...$record,
                'points' => round($record['points'], 2),
                'achievement_tier' => $this->achievementTiers->forPoints((float) $record['points']),
            ]);
        $allRankedFaculty = $this->applyCompetitionRanks($allRankedFaculty);

        if ($viewer->isFaculty()) {
            $collegeRanked = $allRankedFaculty
                ->filter(fn (array $record): bool => $record['college_id'] === (int) $collegeId)
                ->values();
            $collegeRanked = $this->applyCompetitionRanks($collegeRanked);
            $facultyPoints = (float) ($totals[$viewer->id]['points'] ?? 0);

            return [
                'scope' => 'self',
                'college_id' => $collegeId ? (int) $collegeId : null,
                'myStanding' => [
                    'college' => $collegeId ? $this->facultyStanding(
                        $collegeRanked,
                        $viewer->id,
                        (int) ($collegeFacultyCounts[$collegeId] ?? 0),
                        $facultyPoints,
                    ) : null,
                    'institution' => $this->facultyStanding($allRankedFaculty, $viewer->id, $activeFaculty->count(), $facultyPoints),
                ],
                'tiers' => $this->achievementTierLegend(),
                'active_faculty_count' => $activeFaculty->count(),
                'colleges' => [],
                'items' => [],
            ];
        }

        $rankedFaculty = $allRankedFaculty;
        if ($scope === 'college') {
            $rankedFaculty = $rankedFaculty
                ->filter(fn (array $record): bool => $record['college_id'] === (int) $collegeId)
                ->values();
            $rankedFaculty = $this->applyCompetitionRanks($rankedFaculty);
        }
        $rankedFaculty = $rankedFaculty->filter(fn (array $record): bool => $record['rank'] <= 10)->all();

        return [
            'scope' => $scope,
            'college_id' => $scope === 'college' ? (int) $collegeId : null,
            'colleges' => $viewer->isAdmin() ? College::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
            'items' => $rankedFaculty,
            'tiers' => $this->achievementTierLegend(),
        ];
    }

    private function medalForRank(int $rank): ?string
    {
        return match ($rank) {
            1 => 'Gold',
            2 => 'Silver',
            3 => 'Platinum',
            default => null,
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private function applyCompetitionRanks($records)
    {
        $previousPoints = null;
        $previousRank = null;

        return $records->values()->map(function (array $record, int $index) use (&$previousPoints, &$previousRank): array {
            $points = round((float) $record['points'], 2);
            $rank = $previousPoints !== null && $points === $previousPoints ? $previousRank : $index + 1;
            $previousPoints = $points;
            $previousRank = $rank;

            return [
                ...$record,
                'rank' => $rank,
                'award' => $this->medalForRank($rank),
                'achievement_tier' => $this->achievementTiers->forPoints($points),
            ];
        });
    }

    /**
     * @return list<array{title: string, minimum: float, next: float|null}>
     */
    private function achievementTierLegend(): array
    {
        return $this->achievementTiers->tiers();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ranked
     * @return array<string, mixed>
     */
    private function facultyStanding($ranked, int $facultyId, int $participants, float $points): array
    {
        $position = $ranked->search(fn (array $record): bool => $record['id'] === $facultyId);
        $standing = $position === false ? null : $ranked->get($position);
        $nextHigher = $position !== false && $position > 0 ? $ranked->get($position - 1) : null;
        $tier = $this->achievementTiers->forPoints($points);

        return [
            'rank' => $position === false ? null : $standing['rank'],
            'participants' => $participants,
            'points' => round($points, 2),
            'award' => $position === false ? null : $standing['award'],
            'achievement_tier' => $tier,
            'points_to_next_rank' => $nextHigher ? round(max(0, $nextHigher['points'] - $points) + 0.01, 2) : 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $totals
     */
    private function addLeaderboardPoints(array &$totals, User $faculty, ?int $submissionCollegeId, float $points): void
    {
        $facultyCollegeId = $faculty->college_id ?? $submissionCollegeId;
        if (! isset($totals[$faculty->id])) {
            $totals[$faculty->id] = [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'academic_rank' => $faculty->academic_rank,
                'designation' => $faculty->designation,
                'college_id' => $facultyCollegeId,
                'college' => $faculty->college?->name,
                'department' => $faculty->department?->name,
                'points' => 0.0,
            ];
        }

        $totals[$faculty->id]['points'] += $points;
    }

    /**
     * @return array<string, int>
     */
    private function facultyStats(User $user): array
    {
        $base = $this->scopedSubmissions($user);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', SubmissionStatus::Draft)->count(),
            'in_progress' => (clone $base)->whereIn('status', [
                SubmissionStatus::Submitted,
                SubmissionStatus::UnderEvaluation,
                SubmissionStatus::Evaluated,
                SubmissionStatus::RevisionRequested,
            ])->count(),
            'approved' => (clone $base)->where('status', SubmissionStatus::Approved)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function coordinatorStats(User $user): array
    {
        $base = ResearchSubmission::query()
            ->where('department_id', $user->department_id ?? 0);

        return [
            'pending' => (clone $base)
                ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::UnderEvaluation])
                ->count(),
            'evaluated' => (clone $base)
                ->where('status', SubmissionStatus::Evaluated)
                ->count(),
            'revision' => (clone $base)
                ->where('status', SubmissionStatus::RevisionRequested)
                ->count(),
            'approved' => (clone $base)
                ->where('status', SubmissionStatus::Approved)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function adminStats(): array
    {
        return [
            'awaiting_approval' => ResearchSubmission::query()
                ->where('status', SubmissionStatus::Evaluated)
                ->count(),
            'approved' => ResearchSubmission::query()
                ->where('status', SubmissionStatus::Approved)
                ->count(),
            'rejected' => ResearchSubmission::query()
                ->where('status', SubmissionStatus::Rejected)
                ->count(),
            'faculty_users' => User::query()->where('role', UserRole::Faculty)->count(),
        ];
    }

    /**
     * @return array{
     *     trend: list<array{period: string, label: string, total: int}>,
     *     statuses: list<array{key: string, label: string, total: int}>,
     *     researchTypes: list<array{key: string, label: string, total: int}>
     * }
     */
    private function analytics(User $user): array
    {
        $now = now()->startOfMonth();
        $firstMonth = $now->copy()->subMonths(11);
        $base = $this->scopedSubmissions($user);
        $monthExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(submitted_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(submitted_at, 'YYYY-MM')",
            default => "strftime('%Y-%m', submitted_at)",
        };

        $monthlyTotals = (clone $base)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$firstMonth->copy()->startOfDay(), now()->endOfDay()])
            ->selectRaw("{$monthExpression} as period, COUNT(*) as total")
            ->groupByRaw($monthExpression)
            ->pluck('total', 'period');

        $trend = collect(range(0, 11))
            ->map(function (int $offset) use ($firstMonth, $monthlyTotals): array {
                $month = $firstMonth->copy()->addMonths($offset);
                $period = $month->format('Y-m');

                return [
                    'period' => $period,
                    'label' => $month->format('M'),
                    'total' => (int) ($monthlyTotals[$period] ?? 0),
                ];
            })
            ->all();

        $statusTotals = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $typeTotals = (clone $base)
            ->selectRaw('research_type, COUNT(*) as total')
            ->groupBy('research_type')
            ->pluck('total', 'research_type');

        return [
            'trend' => $trend,
            'statuses' => collect(SubmissionStatus::cases())
                ->map(fn (SubmissionStatus $status): array => [
                    'key' => $status->value,
                    'label' => $status->label(),
                    'total' => (int) ($statusTotals[$status->value] ?? 0),
                ])
                ->all(),
            'researchTypes' => ResearchType::withTrashed()->whereIn('slug', $typeTotals->keys())->get(['slug', 'name'])
                ->map(fn (ResearchType $type): array => [
                    'key' => $type->slug,
                    'label' => $type->name,
                    'total' => (int) $typeTotals[$type->slug],
                ])
                ->sortByDesc('total')
                ->take(8)
                ->values()
                ->all(),
        ];
    }

    private function scopedSubmissions(User $user): Builder
    {
        $query = ResearchSubmission::query();

        if ($user->isFaculty()) {
            $query->where(function ($inner) use ($user) {
                $inner->where('faculty_id', $user->id)
                    ->orWhereHas('authorshipAllocations', fn ($authors) => $authors
                        ->where('faculty_id', $user->id)
                        ->whereHas('submission', fn ($submission) => $submission
                            ->whereNot('status', SubmissionStatus::Draft)
                            ->whereNull('deleted_at')));
            });
        } elseif ($user->isCoordinator()) {
            $query->where('department_id', $user->department_id ?? 0);
        }

        return $query;
    }
}
