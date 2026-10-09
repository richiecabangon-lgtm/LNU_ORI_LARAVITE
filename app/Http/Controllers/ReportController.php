<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
use App\Models\FpesReportSetting;
use App\Models\ResearchSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Role-based involvements have no publication date, so they fall back to when the record was approved or submitted. */
    private const REPORT_DATE_SQL = 'COALESCE(publication_date, DATE(approved_at), DATE(submitted_at), DATE(created_at))';

    public function fpes(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'period' => ['sometimes', Rule::in(['monthly', 'quarterly', 'semiannual', 'annual'])],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $period = $filters['period'] ?? 'monthly';
        $year = (int) ($filters['year'] ?? now()->year);
        [$start, $end, $periodLabel] = $this->periodBounds($period, $year, $filters);

        $query = ResearchSubmission::query()
            ->with([
                'faculty:id,name,email,employee_id,designation,college_id,department_id,orcid_url,google_scholar_url,institutional_profile_url',
                'college:id,name',
                'department:id,name,college_id',
                'researchType:slug,name',
                'evaluation:id,research_submission_id,coordinator_id,score,awarded_points,remarks,evaluated_at',
                'evaluation.coordinator:id,name',
                'approval:id,research_submission_id,admin_id,final_points,decision,remarks,decided_at',
                'approval.admin:id,name',
                'authorshipAllocations:id,research_submission_id,faculty_id,allocated_points',
                'sdgs:id,number,name,image_path',
                'researchAgendas:id,name,image_path',
            ])
            ->whereRaw(self::REPORT_DATE_SQL.' between ? and ?', [$start->toDateString(), $end->toDateString()])
            ->whereNot('status', 'draft');

        if ($user->role === UserRole::Faculty) {
            $query->where(function ($inner) use ($user) {
                $inner->where('faculty_id', $user->id)
                    ->orWhereHas('authorshipAllocations', fn ($authors) => $authors->where('faculty_id', $user->id));
            });
        } elseif ($user->role === UserRole::Coordinator) {
            $query->where('department_id', $user->department_id ?? 0);
        } else {
            if (! empty($filters['college_id'])) {
                $query->where('college_id', $filters['college_id']);
            }

            if (! empty($filters['department_id'])) {
                $query->where('department_id', $filters['department_id']);
            }
        }

        $submissions = $query->orderBy('department_id')->orderBy('faculty_id')->orderByRaw(self::REPORT_DATE_SQL)->get();
        $submissions->each(function (ResearchSubmission $submission) use ($user): void {
            $submission->setAttribute('report_date', ($submission->publication_date ?? $submission->approved_at ?? $submission->submitted_at ?? $submission->created_at)?->toDateString());
            $points = $submission->fpesPoints();
            if ($user->isFaculty()) {
                $points = $submission->pointsForFaculty($user);
                $submission->setRelation('faculty', $user);
            }

            $submission->setAttribute('fpes_points', $points);
        });
        $settings = FpesReportSetting::query()
            ->with(['preparedByUser:id,name', 'reviewedByUser:id,name', 'approvedByUser:id,name'])
            ->first() ?? new FpesReportSetting;
        $settings->setAttribute('logo_url', $settings->logo_path
            ? '/storage/'.ltrim($settings->logo_path, '/')
            : null);
        $settings->setAttribute('university_logo_url', $settings->university_logo_path
            ? '/storage/'.ltrim($settings->university_logo_path, '/')
            : null);
        $signatoryUsers = User::query()->where('is_active', true)->whereIn('role', [UserRole::Faculty, UserRole::Coordinator, UserRole::Admin])
            ->orderBy('name')->get(['id', 'name', 'role']);
        $fallbackPrepared = $settings->preparedByUser?->name ?? $settings->prepared_by_name;
        $fallbackApproved = $settings->approvedByUser?->name ?? $settings->approved_by_name;
        $collegeIds = $user->isFaculty()
            ? collect([$user->college_id])->filter()->values()
            : $submissions->pluck('college_id')->filter()->unique()->values();
        $departmentIds = $user->isFaculty()
            ? collect([$user->department_id])->filter()->values()
            : $submissions->pluck('department_id')->filter()->unique()->values();
        $coordinatorNames = $this->coordinatorNamesForColleges($collegeIds->all(), $departmentIds->all());
        $signatories = [
            'prepared_by' => $user->isFaculty() ? $user->name : ($user->isCoordinator() || $user->isAdmin() ? $user->name : ($fallbackPrepared ?: '')),
            'reviewed_by' => $user->isCoordinator()
                ? $user->name
                : ($coordinatorNames->first() ?? $settings->reviewedByUser?->name ?? $settings->reviewed_by_name ?? ''),
            'approved_by' => $fallbackApproved ?? $settings->approved_by_name ?? '',
        ];

        return Inertia::render('Reports/Fpes', [
            'submissions' => $submissions,
            'settings' => $settings,
            'signatories' => $signatories,
            'period' => [
                'type' => $period,
                'label' => $periodLabel,
                'year' => $year,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'filters' => [
                'period' => $period,
                'year' => $year,
                'month' => $filters['month'] ?? $start->month,
                'quarter' => $filters['quarter'] ?? (int) ceil($start->month / 3),
                'semester' => $filters['semester'] ?? ($start->month < 7 ? 1 : 2),
                'college_id' => $filters['college_id'] ?? null,
                'department_id' => $filters['department_id'] ?? null,
            ],
            'scope' => $user->role->value,
            'colleges' => $user->isAdmin()
                ? College::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'departments' => $user->isAdmin()
                ? Department::query()->orderBy('name')->get(['id', 'name', 'college_id'])
                : [],
            'coordinators' => $user->isAdmin()
                ? User::query()->where('role', UserRole::Coordinator)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id'])
                : [],
        ]);
    }

    public function settings(): Response
    {
        $settings = FpesReportSetting::query()->with(['preparedByUser:id,name', 'reviewedByUser:id,name', 'approvedByUser:id,name'])->first() ?? new FpesReportSetting;
        $settings->setAttribute('logo_url', $settings->logo_path
            ? '/storage/'.ltrim($settings->logo_path, '/')
            : null);
        $settings->setAttribute('university_logo_url', $settings->university_logo_path
            ? '/storage/'.ltrim($settings->university_logo_path, '/')
            : null);

        return Inertia::render('Admin/Reports/Settings', [
            'settings' => $settings,
            'signatoryUsers' => User::query()->where('is_active', true)->whereIn('role', [UserRole::Faculty, UserRole::Coordinator, UserRole::Admin])->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'office_name' => ['required', 'string', 'max:255'],
            'report_heading' => ['required', 'string', 'max:255'],
            'report_subheading' => ['nullable', 'string', 'max:255'],
            'prepared_by_title' => ['required', 'string', 'max:100'],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'reviewed_by_title' => ['required', 'string', 'max:100'],
            'reviewed_by_name' => ['nullable', 'string', 'max:255'],
            'reviewed_by_user_id' => ['nullable', 'exists:users,id'],
            'approved_by_title' => ['required', 'string', 'max:100'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
            'approved_by_user_id' => ['required', 'exists:users,id'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'university_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_university_logo' => ['sometimes', 'boolean'],
        ]);

        $signatoryRoles = [
            'prepared_by_user_id' => [UserRole::Admin->value, UserRole::Faculty->value],
            'reviewed_by_user_id' => [UserRole::Coordinator->value],
            'approved_by_user_id' => [UserRole::Admin->value],
        ];
        foreach ($signatoryRoles as $field => $roles) {
            if (! empty($data[$field]) && ! User::query()->whereKey($data[$field])->where('is_active', true)->whereIn('role', $roles)->exists()) {
                throw ValidationException::withMessages([
                    $field => 'Select an active user with the appropriate report signatory role.',
                ]);
            }
        }

        $settings = FpesReportSetting::query()->firstOrNew(['id' => 1]);
        $previousLogoPath = $settings->logo_path;
        $previousUniversityLogoPath = $settings->university_logo_path;
        unset($data['logo'], $data['remove_logo'], $data['university_logo'], $data['remove_university_logo']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('fpes/branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }

        if ($request->hasFile('university_logo')) {
            $data['university_logo_path'] = $request->file('university_logo')->store('fpes/branding', 'public');
        } elseif ($request->boolean('remove_university_logo')) {
            $data['university_logo_path'] = null;
        }

        $settings->fill($data)->save();

        if ($previousLogoPath && $previousLogoPath !== $settings->logo_path) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        if ($previousUniversityLogoPath && $previousUniversityLogoPath !== $settings->university_logo_path) {
            Storage::disk('public')->delete($previousUniversityLogoPath);
        }

        return back()->with('success', 'FPES report settings saved.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function periodBounds(string $period, int $year, array $filters): array
    {
        if ($period === 'monthly') {
            $start = CarbonImmutable::create($year, (int) ($filters['month'] ?? now()->month), 1);
            $end = $start->endOfMonth();
        } elseif ($period === 'quarterly') {
            $quarter = (int) ($filters['quarter'] ?? ceil(now()->month / 3));
            $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1);
            $end = $start->addMonths(2)->endOfMonth();
        } elseif ($period === 'semiannual') {
            $semester = (int) ($filters['semester'] ?? (now()->month < 7 ? 1 : 2));
            $start = CarbonImmutable::create($year, $semester === 1 ? 1 : 7, 1);
            $end = $start->addMonths(5)->endOfMonth();
        } else {
            $start = CarbonImmutable::create($year, 1, 1);
            $end = $start->endOfYear();
        }

        return [$start, $end, $start->format('F j, Y').' - '.$end->format('F j, Y')];
    }

    /**
     * @param  list<int>  $collegeIds
     * @param  list<int>  $departmentIds
     * @return Collection<int, string>
     */
    private function coordinatorNamesForColleges(array $collegeIds, array $departmentIds): Collection
    {
        if ($collegeIds === []) {
            return collect();
        }

        return User::query()
            ->where('role', UserRole::Coordinator)
            ->where('is_active', true)
            ->where(function ($query) use ($collegeIds): void {
                $query->whereIn('college_id', $collegeIds)
                    ->orWhereHas('department', fn ($department) => $department->whereIn('college_id', $collegeIds));
            })
            ->with('department:id,college_id')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (User $coordinator): ?int => $coordinator->college_id ?? $coordinator->department?->college_id)
            ->map(fn (Collection $coordinators): string => $coordinators
                ->sortBy(fn (User $coordinator): int => in_array((int) $coordinator->department_id, $departmentIds, true) ? 0 : 1)
                ->first()
                ->name)
            ->values();
    }
}
