<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchSubmissionAuthor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function archiveFixture(): array
{
    $college = College::query()->create(['code' => 'ARC', 'name' => 'Archive College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'ARC', 'name' => 'Archive Department']);
    $otherDepartment = Department::query()->create(['college_id' => $college->id, 'code' => 'OTH', 'name' => 'Other Department']);
    $year = AcademicYear::query()->create(['name' => 'Archive year', 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31', 'is_current' => true, 'is_active' => true]);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $otherFaculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $otherDepartment->id]);
    $coordinator = User::factory()->coordinator()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $admin = User::factory()->admin()->create();

    return compact('college', 'department', 'otherDepartment', 'year', 'faculty', 'otherFaculty', 'coordinator', 'admin');
}

function archiveSubmission(array $fixture, string $title, array $attributes = []): ResearchSubmission
{
    return ResearchSubmission::query()->create([
        'faculty_id' => $fixture['faculty']->id,
        'academic_year_id' => $fixture['year']->id,
        'college_id' => $fixture['college']->id,
        'department_id' => $fixture['department']->id,
        'title' => $title,
        'research_type' => 'journal_article',
        'authors' => [$fixture['faculty']->name],
        'publication_date' => '2024-06-15',
        'status' => SubmissionStatus::Approved,
        ...$attributes,
    ]);
}

function archiveRange(): array
{
    return ['start_date' => '2024-06-01', 'end_date' => '2024-06-30'];
}

it('previews inclusive report dates with the report precedence and approved status only', function () {
    $fixture = archiveFixture();
    $start = archiveSubmission($fixture, 'Start', ['publication_date' => '2024-06-01', 'approved_at' => '2024-05-01']);
    $end = archiveSubmission($fixture, 'End', ['publication_date' => '2024-06-30']);
    $approved = archiveSubmission($fixture, 'Approval date', ['publication_date' => null, 'approved_at' => '2024-06-10 23:59:59', 'submitted_at' => '2024-05-01']);
    $submitted = archiveSubmission($fixture, 'Submission date', ['publication_date' => null, 'approved_at' => null, 'submitted_at' => '2024-06-11 08:00:00']);
    $created = archiveSubmission($fixture, 'Creation date', ['publication_date' => null, 'approved_at' => null, 'submitted_at' => null]);
    $created->forceFill(['created_at' => '2024-06-12 08:00:00'])->save();
    archiveSubmission($fixture, 'Before', ['publication_date' => '2024-05-31', 'approved_at' => '2024-06-10']);
    archiveSubmission($fixture, 'After', ['publication_date' => '2024-07-01']);
    archiveSubmission($fixture, 'Pending', ['status' => SubmissionStatus::Submitted]);

    $response = $this->actingAs($fixture['admin'])->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())
        ->assertOk()->assertJsonPath('start_date', '2024-06-01')->assertJsonPath('end_date', '2024-06-30');
    expect($response->json('ids'))->toBe([$start->id, $end->id, $approved->id, $submitted->id, $created->id]);
    $this->get(route('reports.fpes', ['period' => 'monthly', 'year' => 2024, 'month' => 6]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->contains('id', $end->id)));
});

it('freezes membership and filters reports and CSV while preserving role scope and restoration audit', function () {
    $fixture = archiveFixture();
    $first = archiveSubmission($fixture, 'Archived first');
    $second = archiveSubmission($fixture, 'Archived other', ['faculty_id' => $fixture['otherFaculty']->id, 'department_id' => $fixture['otherDepartment']->id]);
    ResearchSubmissionAuthor::query()->create(['research_submission_id' => $first->id, 'faculty_id' => $fixture['otherFaculty']->id, 'name' => $fixture['otherFaculty']->name, 'author_order' => 1, 'allocated_points' => 1]);
    $pending = archiveSubmission($fixture, 'Pending', ['status' => SubmissionStatus::Submitted]);
    $this->actingAs($fixture['admin']);
    $preview = $this->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertOk()->json();
    $this->postJson(route('admin.reports.fpes.archives.store'), $preview)->assertOk();
    $archive = DB::table('fpes_archives')->first();
    expect($archive->created_by)->toBe($fixture['admin']->id)
        ->and($archive->start_date)->toBe('2024-06-01')
        ->and(DB::table('fpes_archive_submissions')->count())->toBe(2);

    $first->update(['publication_date' => '2024-07-01']);
    $pending->update(['status' => SubmissionStatus::Approved]);
    $filters = ['period' => 'annual', 'year' => 2024];
    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('submissions', fn ($records) => $records->pluck('id')->all() === [$pending->id]));
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['title']]))->assertOk()->streamedContent();
    expect($csv)->toContain('Pending')->not->toContain('Archived first')->not->toContain('Archived other');
    $csv = $this->get(route('admin.reports.fpes.export', [...$filters, 'include_archived' => 1, 'columns' => ['title']]))->assertOk()->streamedContent();
    expect($csv)->toContain('Pending')->toContain('Archived first')->toContain('Archived other');

    foreach ([$fixture['faculty'], $fixture['coordinator']] as $user) {
        $this->actingAs($user)->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->pluck('id')->all() === [$pending->id]));
        $this->get(route('reports.fpes', [...$filters, 'include_archived' => 1]))->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 2 && $records->contains('id', $first->id) && ! $records->contains('id', $second->id)));
    }
    $this->actingAs($fixture['otherFaculty'])->get(route('reports.fpes', [...$filters, 'include_archived' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->count() === 2 && $records->contains('id', $first->id) && $records->contains('id', $second->id)));

    $this->actingAs($fixture['admin'])->postJson(route('admin.reports.fpes.archives.restore', $archive->id))->assertOk();
    expect(DB::table('fpes_archives')->where('id', $archive->id)->first()->restored_by)->toBe($fixture['admin']->id)
        ->and(DB::table('fpes_archives')->where('id', $archive->id)->first()->restored_at)->not->toBeNull()
        ->and(DB::table('fpes_archive_submissions')->count())->toBe(2);
    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page->where('submissions', fn ($records) => $records->count() === 3));
    $this->postJson(route('admin.reports.fpes.archives.restore', $archive->id))->assertUnprocessable();
    $this->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertOk()->assertJsonPath('count', 2);
});

it('rejects overlapping active ranges and membership even after report dates change', function () {
    $fixture = archiveFixture();
    $submission = archiveSubmission($fixture, 'Moving date');
    $this->actingAs($fixture['admin']);
    $preview = $this->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertOk()->json();
    $this->postJson(route('admin.reports.fpes.archives.store'), $preview)->assertOk();
    $this->postJson(route('admin.reports.fpes.archives.preview'), ['start_date' => '2024-06-30', 'end_date' => '2024-07-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('start_date');
    $submission->update(['publication_date' => '2024-07-15']);
    $this->postJson(route('admin.reports.fpes.archives.preview'), ['start_date' => '2024-07-01', 'end_date' => '2024-07-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->postJson(route('admin.reports.fpes.archives.store'), ['start_date' => '2024-07-01', 'end_date' => '2024-07-31', 'ids' => [$submission->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('ids');
    expect(DB::table('fpes_archives')->count())->toBe(1);
});

it('rejects stale previews and invalid archive inputs', function () {
    $fixture = archiveFixture();
    $submission = archiveSubmission($fixture, 'First approval');
    $pending = archiveSubmission($fixture, 'Later approval', ['status' => SubmissionStatus::Submitted]);
    $this->actingAs($fixture['admin']);
    $preview = $this->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertOk()->json();
    $pending->update(['status' => SubmissionStatus::Approved]);
    $this->postJson(route('admin.reports.fpes.archives.store'), $preview)->assertUnprocessable()->assertJsonValidationErrors('ids');
    $pending->update(['status' => SubmissionStatus::Submitted]);
    $this->postJson(route('admin.reports.fpes.archives.store'), [...archiveRange(), 'ids' => [$submission->id, $pending->id]])->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->postJson(route('admin.reports.fpes.archives.store'), [...archiveRange(), 'ids' => [$submission->id, $submission->id]])->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    $this->postJson(route('admin.reports.fpes.archives.preview'), ['start_date' => '2024-02-30', 'end_date' => '2024-01-01'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
    $this->postJson(route('admin.reports.fpes.archives.preview'), ['start_date' => '2024-07-01', 'end_date' => '2024-06-30'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    $this->postJson(route('admin.reports.fpes.archives.store'), [...archiveRange(), 'ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->getJson(route('reports.fpes', ['include_archived' => 'yes']))->assertUnprocessable()->assertJsonValidationErrors('include_archived');
    expect(DB::table('fpes_archives')->count())->toBe(0);
});

it('restricts archive operations to administrators', function () {
    $fixture = archiveFixture();
    $this->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertUnauthorized();
    foreach ([$fixture['faculty'], $fixture['coordinator']] as $user) {
        $this->actingAs($user)->postJson(route('admin.reports.fpes.archives.preview'), archiveRange())->assertForbidden();
        $this->postJson(route('admin.reports.fpes.archives.store'), [...archiveRange(), 'ids' => [1]])->assertForbidden();
        $this->postJson(route('admin.reports.fpes.archives.restore', 1))->assertForbidden();
    }
});
