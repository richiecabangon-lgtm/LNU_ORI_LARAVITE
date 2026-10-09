<?php

use App\Enums\EvaluationRecommendation;
use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\Approval;
use App\Models\College;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\FpesReportSetting;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function makeFpesReportFixture(): array
{
    $firstCollege = College::query()->create(['code' => 'C1', 'name' => 'College One']);
    $secondCollege = College::query()->create(['code' => 'C2', 'name' => 'College Two']);
    $firstDepartment = Department::query()->create(['college_id' => $firstCollege->id, 'code' => 'D1', 'name' => 'Department One']);
    $secondDepartment = Department::query()->create(['college_id' => $secondCollege->id, 'code' => 'D2', 'name' => 'Department Two']);
    $academicYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);
    $firstFaculty = User::factory()->faculty()->create([
        'college_id' => $firstCollege->id,
        'department_id' => $firstDepartment->id,
    ]);
    $secondFaculty = User::factory()->faculty()->create([
        'college_id' => $secondCollege->id,
        'department_id' => $secondDepartment->id,
    ]);
    $coordinator = User::factory()->coordinator()->create([
        'college_id' => $firstCollege->id,
        'department_id' => $firstDepartment->id,
    ]);
    User::factory()->coordinator()->create([
        'name' => 'A College Coordinator',
        'college_id' => $firstCollege->id,
    ]);

    foreach ([[$firstFaculty, $firstDepartment, $firstCollege], [$secondFaculty, $secondDepartment, $secondCollege]] as [$faculty, $department, $college]) {
        ResearchSubmission::query()->create([
            'faculty_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
            'title' => $department->name.' output',
            'research_type' => 'journal_article',
            'authors' => [$faculty->name],
            'publication_date' => '2026-10-05',
            'submitted_at' => now(),
            'status' => SubmissionStatus::Submitted,
            'claimed_points' => 5,
        ]);
    }

    return compact('firstCollege', 'secondCollege', 'firstDepartment', 'secondDepartment', 'firstFaculty', 'secondFaculty', 'coordinator');
}

it('limits faculty FPES reports to their own research outputs', function () {
    $fixture = makeFpesReportFixture();

    $this->actingAs($fixture['firstFaculty'])
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Fpes')
            ->where('submissions', fn ($records) => $records->count() === 1
                && $records->first()['faculty']['id'] === $fixture['firstFaculty']->id)
            ->where('signatories.prepared_by', $fixture['firstFaculty']->name)
            ->where('signatories.reviewed_by', $fixture['coordinator']->name));
});

it('uses the one configured administrator for the approved-by signature', function () {
    $fixture = makeFpesReportFixture();
    $admin = User::factory()->admin()->create(['name' => 'University Approver']);
    FpesReportSetting::query()->create([
        'institution_name' => 'Leyte Normal University',
        'office_name' => 'Office of Research and Innovation',
        'report_heading' => 'Faculty Performance Evaluation Report',
        'prepared_by_title' => 'Prepared by',
        'reviewed_by_title' => 'Evaluated by',
        'approved_by_title' => 'Approved by',
        'approved_by_user_id' => $admin->id,
    ]);

    $this->actingAs($fixture['firstFaculty'])
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('signatories.approved_by', 'University Approver'));
});

it('limits coordinator FPES reports to their assigned department', function () {
    $fixture = makeFpesReportFixture();

    $this->actingAs($fixture['coordinator'])
        ->get(route('reports.fpes', [
            'period' => 'annual',
            'year' => 2026,
            'department_id' => $fixture['secondDepartment']->id,
            'college_id' => $fixture['secondCollege']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 1
                && $records->first()['department']['id'] === $fixture['firstDepartment']->id));
});

it('filters administrator FPES reports to the selected college', function () {
    $fixture = makeFpesReportFixture();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.fpes', ['period' => 'quarterly', 'year' => 2026, 'quarter' => 4, 'college_id' => $fixture['secondCollege']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 1
                && $records->first()['college']['id'] === $fixture['secondCollege']->id));
});

it('excludes rejected submissions from FPES points even when stored final points are nonzero', function () {
    $fixture = makeFpesReportFixture();
    $submission = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $admin = User::factory()->admin()->create();

    $submission->update(['status' => SubmissionStatus::Rejected]);
    Approval::query()->create([
        'research_submission_id' => $submission->id,
        'admin_id' => $admin->id,
        'decision' => 'rejected',
        'final_points' => 180,
        'remarks' => 'Rejected for test coverage.',
        'decided_at' => now(),
    ]);

    $this->actingAs($fixture['firstFaculty'])
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 1
                && (float) $records->first()['fpes_points'] === 0.0));
});

it('includes decision makers, rejection reasons, and attributed report comments', function () {
    $fixture = makeFpesReportFixture();
    $submission = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $admin = User::factory()->admin()->create(['name' => 'University Approver']);
    $submission->update([
        'status' => SubmissionStatus::Rejected,
        'faculty_remarks' => 'Faculty confirmation note.',
    ]);

    Evaluation::query()->create([
        'research_submission_id' => $submission->id,
        'coordinator_id' => $fixture['coordinator']->id,
        'recommendation' => EvaluationRecommendation::RecommendRejection,
        'remarks' => 'Coordinator evaluation comment.',
        'evaluated_at' => now(),
    ]);
    Approval::query()->create([
        'research_submission_id' => $submission->id,
        'admin_id' => $admin->id,
        'decision' => 'rejected',
        'final_points' => 10,
        'remarks' => 'Missing required evidence.',
        'decided_at' => now(),
    ]);

    $this->actingAs($fixture['firstFaculty'])
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.approval.admin.name', 'University Approver')
            ->where('submissions.0.approval.remarks', 'Missing required evidence.')
            ->where('submissions.0.evaluation.coordinator.name', $fixture['coordinator']->name)
            ->where('submissions.0.evaluation.remarks', 'Coordinator evaluation comment.')
            ->where('submissions.0.faculty_remarks', 'Faculty confirmation note.'));
});

it('allows only administrators to update shared FPES settings', function () {
    $coordinator = User::factory()->coordinator()->create();

    $this->actingAs($coordinator)
        ->put(route('admin.reports.settings.update'), ['report_heading' => 'Altered heading'])
        ->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->put(route('admin.reports.settings.update'), [
            'institution_name' => 'Leyte Normal University',
            'office_name' => 'Office of Research and Innovation',
            'report_heading' => 'Faculty Performance Evaluation Sheet',
            'report_subheading' => 'Academic Year 2026-2027',
            'prepared_by_title' => 'Prepared by',
            'prepared_by_name' => 'ORI Coordinator',
            'reviewed_by_title' => 'Reviewed by',
            'reviewed_by_name' => 'ORI Director',
            'approved_by_title' => 'Approved by',
            'approved_by_name' => 'Vice President',
            'approved_by_user_id' => $admin->id,
            'footer_text' => 'Official report',
        ])
        ->assertRedirect();

    expect(FpesReportSetting::query()->first()->report_heading)
        ->toBe('Faculty Performance Evaluation Sheet');
});

it('makes the administrator-managed ORI logo available to printable FPES reports', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $settings = [
        'institution_name' => 'Leyte Normal University',
        'office_name' => 'Office of Research and Innovation',
        'report_heading' => 'Faculty Performance Evaluation Sheet',
        'prepared_by_title' => 'Prepared by',
        'reviewed_by_title' => 'Reviewed by',
        'approved_by_title' => 'Approved by',
    ];

    $this->actingAs($admin)
        ->put(route('admin.reports.settings.update'), [
            ...$settings,
            'approved_by_user_id' => $admin->id,
            'logo' => UploadedFile::fake()->image('ori-logo.png'),
            'university_logo' => UploadedFile::fake()->image('university-logo.png'),
        ])
        ->assertRedirect();

    $logoPath = FpesReportSetting::query()->firstOrFail()->logo_path;
    $universityLogoPath = FpesReportSetting::query()->firstOrFail()->university_logo_path;
    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($universityLogoPath);

    Auth::logout();
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('app.logo_url', '/storage/'.$logoPath));

    $faculty = User::factory()->faculty()->create();
    $this->actingAs($faculty)
        ->get(route('reports.fpes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.logo_url', '/storage/'.$logoPath)
            ->where('settings.university_logo_url', '/storage/'.$universityLogoPath));

    $this->actingAs($admin)
        ->put(route('admin.reports.settings.update'), [...$settings, 'approved_by_user_id' => $admin->id, 'remove_logo' => true])
        ->assertRedirect();

    expect(FpesReportSetting::query()->firstOrFail()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

it('shows coordinators only department-scoped dashboard analytics and evaluations', function () {
    $fixture = makeFpesReportFixture();

    $this->actingAs($fixture['coordinator'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending', 1)
            ->where('recent', fn ($recent) => count($recent) === 1
                && $recent[0]['department_id'] === $fixture['firstDepartment']->id)
            ->where('analytics.statuses', fn ($statuses) => collect($statuses)->firstWhere('key', 'submitted')['total'] === 1)
            ->where('analytics.researchTypes', fn ($types) => collect($types)->firstWhere('key', 'journal_article')['total'] === 1)
            ->where('analytics.trend', fn ($trend) => collect($trend)->sum('total') === 1));

    $foreignSubmission = ResearchSubmission::query()
        ->where('faculty_id', $fixture['secondFaculty']->id)
        ->firstOrFail();

    $this->get(route('coordinator.evaluations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.data', fn ($records) => count($records) === 1
                && $records[0]['department_id'] === $fixture['firstDepartment']->id));

    $this->get(route('coordinator.evaluations.show', $foreignSubmission))
        ->assertForbidden();
});

it('includes submissions without a publication date using their submission date', function () {
    $fixture = makeFpesReportFixture();
    ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->update(['publication_date' => null, 'submitted_at' => '2026-10-07 09:00:00']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 2
                && $records->firstWhere('faculty_id', $fixture['firstFaculty']->id)['report_date'] === '2026-10-07'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 9]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->count() === 0));
});
