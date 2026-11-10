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

it('identifies who submitted a coauthored record while retaining faculty point allocations', function () {
    $fixture = makeFpesReportFixture();
    $submitter = $fixture['secondFaculty'];
    $viewer = $fixture['firstFaculty'];
    $submission = ResearchSubmission::query()->where('faculty_id', $submitter->id)->firstOrFail();
    $submission->authorshipAllocations()->createMany([
        ['name' => $submitter->name, 'faculty_id' => $submitter->id, 'author_order' => 1, 'allocated_points' => 3],
        ['name' => $viewer->name, 'faculty_id' => $viewer->id, 'author_order' => 2, 'allocated_points' => 2],
    ]);

    $this->actingAs($viewer)
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('submissions', function ($records) use ($viewer, $submitter, $submission) {
            $own = $records->firstWhere('faculty_id', $viewer->id);
            $coauthored = $records->firstWhere('id', $submission->id);

            return $records->count() === 2
                && $own['submitted_by_name'] === null
                && $coauthored['submitted_by_name'] === $submitter->name
                && $coauthored['faculty']['id'] === $viewer->id
                && (float) $coauthored['fpes_points'] === 2.0;
        }));

    $this->actingAs(User::factory()->faculty()->create())
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->isEmpty()));
});

it('includes the scopus profile in authorized report faculty data', function () {
    $fixture = makeFpesReportFixture();
    $url = 'https://www.scopus.com/authid/detail.uri?authorId=123';
    $fixture['firstFaculty']->update(['scopus_url' => $url]);

    $this->actingAs($fixture['coordinator'])
        ->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2026, 'month' => 10]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.0.faculty.scopus_url', $url));
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

function fpesCsvRows(string $content): array
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, substr($content, 3));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

it('exports only the selected columns and the same scoped report rows', function () {
    $fixture = makeFpesReportFixture();
    $submission = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $submission->update([
        'title' => "Pananaliksik, \"Leyte\"\nStudy",
        'publication_date' => null,
        'submitted_at' => '2026-10-07 09:00:00',
        'doi' => '10.1234/example',
        'issn_isbn' => '1234-5678',
        'indexing' => 'Scopus',
        'keywords' => 'wika, kultura',
        'funding_source' => 'external',
        'funding_organization' => 'LNU',
    ]);
    $filters = ['period' => 'monthly', 'year' => 2026, 'month' => 10, 'college_id' => $fixture['firstCollege']->id, 'department_id' => $fixture['firstDepartment']->id];
    $columns = ['title', 'date', 'researcher', 'reference', 'doi', 'issn_isbn', 'indexing', 'keywords', 'funding_source', 'funding_organization'];
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('submissions', fn ($records) => $records->count() === 1 && $records->first()['id'] === $submission->id));
    $response = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => $columns]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('cache-control', 'no-store, private');
    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($response->headers->get('content-disposition'))->toContain('fpes-2026-10-01-2026-10-31.csv')
        ->and(fpesCsvRows($content))->toBe([
            ['Title', 'Report date', 'Researcher', 'Reference', 'DOI', 'ISSN/ISBN', 'Indexing', 'Keywords', 'Funding source', 'Funder'],
            ["Pananaliksik, \"Leyte\"\nStudy", '2026-10-07', $fixture['firstFaculty']->name, $submission->reference_code, '10.1234/example', '1234-5678', 'Scopus', 'wika, kultura', 'external', 'LNU'],
        ]);
});

it('exports only report rows within the requested period and excludes drafts', function (array $filters) {
    $fixture = makeFpesReportFixture();
    ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->update(['status' => SubmissionStatus::Draft]);
    $submission = ResearchSubmission::query()->where('faculty_id', $fixture['secondFaculty']->id)->firstOrFail();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('submissions', fn ($records) => $records->count() === 1 && $records->first()['id'] === $submission->id));
    $response = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference']]))->assertOk();

    expect(fpesCsvRows($response->streamedContent()))->toBe([['Reference'], [$submission->reference_code]]);
})->with([
    'monthly' => [['period' => 'monthly', 'year' => 2026, 'month' => 10]],
    'quarterly' => [['period' => 'quarterly', 'year' => 2026, 'quarter' => 4]],
    'semiannual' => [['period' => 'semiannual', 'year' => 2026, 'semester' => 2]],
    'annual' => [['period' => 'annual', 'year' => 2026]],
]);

it('returns a header-only CSV for an empty report', function () {
    makeFpesReportFixture();
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.fpes.export', ['period' => 'annual', 'year' => 2025, 'columns' => ['title', 'researcher']]))
        ->assertOk();

    expect(fpesCsvRows($response->streamedContent()))->toBe([['Title', 'Researcher']]);
});

it('applies college and department filters together without leaking other reports', function () {
    $fixture = makeFpesReportFixture();
    $filters = ['period' => 'annual', 'year' => 2026, 'college_id' => $fixture['firstCollege']->id, 'department_id' => $fixture['secondDepartment']->id];
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('submissions', fn ($records) => $records->isEmpty()));
    $response = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['title']]))->assertOk();

    expect(fpesCsvRows($response->streamedContent()))->toBe([['Title']]);
});

it('prevents spreadsheet formulas in exported text', function (string $value) {
    $fixture = makeFpesReportFixture();
    ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->update(['keywords' => $value]);
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.fpes.export', ['period' => 'annual', 'year' => 2026, 'college_id' => $fixture['firstCollege']->id, 'columns' => ['keywords']]))
        ->assertOk();

    expect(fpesCsvRows($response->streamedContent()))->toBe([['Keywords'], ["'".$value]]);
})->with(['=SUM(1,2)', '+1', '-1', '@SUM(1)', " \t=1", "\rvalue", "\u{00A0}@formula", "\xFF=1"]);

it('refuses CSV export to unauthenticated and non-admin users', function () {
    $url = route('admin.reports.fpes.export', ['columns' => ['title']]);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->faculty()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->coordinator()->create())->get($url)->assertForbidden();
});

it('rejects invalid CSV columns and report filters', function (array $parameters, string $error) {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.reports.fpes.export', $parameters))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'missing columns' => [[], 'columns'],
    'unknown expression' => [['columns' => ['title', 'title from users']], 'columns.1'],
    'duplicate columns' => [['columns' => ['title', 'title']], 'columns.0'],
    'invalid period' => [['columns' => ['title'], 'period' => 'decade'], 'period'],
    'invalid college' => [['columns' => ['title'], 'college_id' => 99999], 'college_id'],
]);

function makeFilteredFpesSubmission(array $fixture, string $title, string $type, ?string $indexing = null): ResearchSubmission
{
    $base = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();

    return ResearchSubmission::query()->create([
        'faculty_id' => $base->faculty_id,
        'academic_year_id' => $base->academic_year_id,
        'college_id' => $base->college_id,
        'department_id' => $base->department_id,
        'title' => $title,
        'research_type' => $type,
        'indexing' => $indexing,
        'authors' => [$fixture['firstFaculty']->name],
        'publication_date' => '2026-10-05',
        'status' => SubmissionStatus::Submitted,
    ]);
}

it('filters reported Scopus documents without including unrelated activities or negative indexing', function (string $role) {
    $fixture = makeFpesReportFixture();
    $first = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $foreign = ResearchSubmission::query()->where('faculty_id', $fixture['secondFaculty']->id)->firstOrFail();
    $first->update(['indexing' => ' ScOpUs ; Web of Science']);
    $foreign->update(['indexing' => 'Scopus']);
    $catalogue = makeFilteredFpesSubmission($fixture, 'Catalogue publication', 'published_research_published_individual_research_scopus_indexed_journal');
    $conference = makeFilteredFpesSubmission($fixture, 'Legacy conference', 'conference_paper', 'Other | SCOPUS');
    makeFilteredFpesSubmission($fixture, 'Non Scopus', 'journal_article', 'non-Scopus');
    makeFilteredFpesSubmission($fixture, 'Not Scopus', 'conference_paper', 'not Scopus');
    makeFilteredFpesSubmission($fixture, 'Substring', 'journal_article', 'Scopus-indexed');
    makeFilteredFpesSubmission($fixture, 'Citation', 'research_citations_citation_in_a_scopus_journal_per_citation', 'Scopus');
    makeFilteredFpesSubmission($fixture, 'Referee', 'refereeing_in_a_peer_reviewed_journal_referee_isi_indexed_scopus_journal_per_article', 'Scopus');
    $user = match ($role) {
        'faculty' => $fixture['firstFaculty'],
        'coordinator' => $fixture['coordinator'],
        'admin' => User::factory()->admin()->create(),
    };
    $expected = [$first->id, $catalogue->id, $conference->id];
    if ($role === 'admin') {
        $expected[] = $foreign->id;
    }
    sort($expected);

    $this->actingAs($user)->get(route('reports.fpes', ['period' => 'annual', 'year' => 2026, 'report_kind' => 'scopus']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.report_kind', 'scopus')
            ->where('submissions', fn ($records) => $records->pluck('id')->sort()->values()->all() === $expected));
})->with(['faculty', 'coordinator', 'admin']);

it('filters only patent types and retains a historical type selection for each role', function (string $role) {
    $fixture = makeFpesReportFixture();
    $legacy = makeFilteredFpesSubmission($fixture, 'Legacy patent', 'patent');
    $accepted = makeFilteredFpesSubmission($fixture, 'Accepted patent', 'intellectual_property_patent_if_accepted');
    $published = makeFilteredFpesSubmission($fixture, 'Published patent', 'intellectual_property_patent_if_published');
    $granted = makeFilteredFpesSubmission($fixture, 'Granted patent', 'intellectual_property_patent_if_granted');
    makeFilteredFpesSubmission($fixture, 'Utility model', 'intellectual_property_utility_model_if_granted');
    $user = match ($role) {
        'faculty' => $fixture['firstFaculty'],
        'coordinator' => $fixture['coordinator'],
        'admin' => User::factory()->admin()->create(),
    };
    $filters = ['period' => 'annual', 'year' => 2026, 'report_kind' => 'patent'];

    $this->actingAs($user)->get(route('reports.fpes', $filters))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.report_kind', 'patent')
            ->where('submissions', fn ($records) => $records->pluck('id')->sort()->values()->all() === [$legacy->id, $accepted->id, $published->id, $granted->id])
            ->where('researchTypes', fn ($types) => $types->contains(fn ($type) => $type['slug'] === 'patent' && $type['deleted_at'] !== null)));
    $this->get(route('reports.fpes', [...$filters, 'research_type' => 'patent']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.research_type', 'patent')
            ->where('submissions', fn ($records) => $records->pluck('id')->all() === [$legacy->id]));
})->with(['faculty', 'coordinator', 'admin']);

it('applies report category and historical type selections to administrator CSV exports', function () {
    $fixture = makeFpesReportFixture();
    $first = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $first->update(['indexing' => 'Scopus']);
    makeFilteredFpesSubmission($fixture, 'Conference record', 'conference_paper', 'Scopus');
    makeFilteredFpesSubmission($fixture, 'Negative record', 'journal_article', 'non-Scopus');
    $filters = ['period' => 'annual', 'year' => 2026, 'report_kind' => 'scopus', 'research_type' => 'journal_article', 'college_id' => $fixture['firstCollege']->id];

    $this->actingAs(User::factory()->admin()->create())->get(route('reports.fpes', $filters))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.report_kind', 'scopus')
            ->where('filters.research_type', 'journal_article')
            ->where('submissions', fn ($records) => $records->pluck('id')->all() === [$first->id]));
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference', 'title']]))->assertOk()->streamedContent();
    expect(fpesCsvRows($csv))->toBe([['Reference', 'Title'], [$first->reference_code, $first->title]]);
});

it('exports only patent records in the selected college', function () {
    $fixture = makeFpesReportFixture();
    $patent = makeFilteredFpesSubmission($fixture, 'Accepted patent', 'intellectual_property_patent_if_accepted');
    $foreign = ResearchSubmission::query()->where('faculty_id', $fixture['secondFaculty']->id)->firstOrFail();
    $foreign->update(['research_type' => 'patent']);
    makeFilteredFpesSubmission($fixture, 'Trademark', 'intellectual_property_trademark');
    $filters = ['period' => 'annual', 'year' => 2026, 'report_kind' => 'patent', 'college_id' => $fixture['firstCollege']->id];

    $this->actingAs(User::factory()->admin()->create())->get(route('reports.fpes', $filters))
        ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->pluck('id')->all() === [$patent->id]));
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference']]))->assertOk()->streamedContent();
    expect(fpesCsvRows($csv))->toBe([['Reference'], [$patent->reference_code]]);
});

it('rejects invalid report categories and research types on report and export', function (string $field, string $value) {
    $this->actingAs(User::factory()->admin()->create());
    $this->getJson(route('reports.fpes', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->getJson(route('admin.reports.fpes.export', [$field => $value, 'columns' => ['title']]))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['report_kind', 'scopus-or-patent'],
    ['research_type', 'unknown_type'],
]);

it('combines report category filters with archive and role scope', function () {
    $fixture = makeFpesReportFixture();
    $first = ResearchSubmission::query()->where('faculty_id', $fixture['firstFaculty']->id)->firstOrFail();
    $foreign = ResearchSubmission::query()->where('faculty_id', $fixture['secondFaculty']->id)->firstOrFail();
    $first->update(['indexing' => 'Scopus', 'status' => SubmissionStatus::Approved]);
    $foreign->update(['indexing' => 'Scopus', 'status' => SubmissionStatus::Approved]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $preview = $this->postJson(route('admin.reports.fpes.archives.preview'), ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'])->assertOk()->json();
    $this->postJson(route('admin.reports.fpes.archives.store'), $preview)->assertOk();
    $filters = ['period' => 'annual', 'year' => 2026, 'report_kind' => 'scopus'];

    foreach ([$fixture['firstFaculty'], $fixture['coordinator'], $admin] as $user) {
        $expected = $user->isAdmin() ? [$first->id, $foreign->id] : [$first->id];
        sort($expected);
        $this->actingAs($user)->get(route('reports.fpes', $filters))
            ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->isEmpty()));
        $this->get(route('reports.fpes', [...$filters, 'include_archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->pluck('id')->sort()->values()->all() === $expected));
    }
    $this->actingAs($admin);
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference']]))->assertOk()->streamedContent();
    expect(fpesCsvRows($csv))->toBe([['Reference']]);
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'include_archived' => 1, 'columns' => ['reference']]))->assertOk()->streamedContent();
    expect($csv)->toContain($first->reference_code)->toContain($foreign->reference_code);
});
