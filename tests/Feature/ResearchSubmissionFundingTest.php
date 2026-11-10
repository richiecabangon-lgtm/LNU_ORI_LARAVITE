<?php

use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function fundingFixture(): array
{
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);
    $faculty = User::factory()->faculty()->create();
    ResearchType::withTrashed()->where('slug', 'journal_article')->update(['is_active' => true, 'deleted_at' => null]);
    ResearchTypePointSetting::query()->updateOrCreate(['research_type' => 'journal_article'], ['points_per_output' => 100]);

    return [$faculty, [
        'title' => 'Research Funding Test',
        'research_type' => 'journal_article',
        'academic_year_id' => $year->id,
        'authors' => [$faculty->name],
        'author_details' => [['name' => $faculty->name, 'email' => $faculty->email, 'allocated_points' => 100]],
        'submit_now' => false,
    ]];
}

it('saves optional funding categories on drafts', function (?string $source, ?string $organization) {
    [$faculty, $payload] = fundingFixture();
    if ($source !== null) {
        $payload['funding_source'] = $source;
        $payload['funding_organization'] = $organization;
    }

    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();

    $submission = ResearchSubmission::query()->firstOrFail();
    expect($submission->funding_source)->toBe($source)
        ->and($submission->funding_organization)->toBe($organization);
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page
        ->where('submission.funding_source', $source)
        ->where('submission.funding_organization', $organization));
})->with([
    'legacy unspecified' => [null, null],
    'personal' => ['personal', null],
    'internal' => ['internal', null],
    'external' => ['external', 'DOST'],
    'maximum length organization' => ['external', str_repeat('a', 255)],
]);

it('updates funding and clears an old external organization when changing category', function () {
    [$faculty, $payload] = fundingFixture();
    $payload['funding_source'] = 'external';
    $payload['funding_organization'] = 'DOST';
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();

    $payload['funding_organization'] = 'CHED';
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->funding_organization)->toBe('CHED');

    unset($payload['funding_source'], $payload['funding_organization']);
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->funding_source)->toBe('external')
        ->and($submission->fresh()->funding_organization)->toBe('CHED');

    $payload['funding_source'] = 'internal';
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->funding_source)->toBe('internal')
        ->and($submission->fresh()->funding_organization)->toBeNull();

    $payload['funding_source'] = 'personal';
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->funding_source)->toBe('personal')
        ->and($submission->fresh()->funding_organization)->toBeNull();
});

it('rejects a partial update that would clear an external organization', function () {
    [$faculty, $payload] = fundingFixture();
    $payload['funding_source'] = 'external';
    $payload['funding_organization'] = 'DOST';
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();

    unset($payload['funding_source']);
    $payload['funding_organization'] = null;
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasErrors('funding_source');
    expect($submission->fresh()->funding_source)->toBe('external')
        ->and($submission->fresh()->funding_organization)->toBe('DOST');
});

it('provides funding to authorized coordinator and admin reviewers', function () {
    [$faculty, $payload] = fundingFixture();
    $college = College::query()->create(['code' => 'FUND', 'name' => 'Funding College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'FUND', 'name' => 'Funding Department']);
    $faculty->update(['college_id' => $college->id, 'department_id' => $department->id]);
    $payload['funding_source'] = 'external';
    $payload['funding_organization'] = 'CHED';
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();

    $this->actingAs(User::factory()->coordinator()->create(['department_id' => $department->id]))
        ->get(route('coordinator.evaluations.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.funding_source', 'external')->where('submission.funding_organization', 'CHED'));
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.approvals.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.funding_source', 'external')->where('submission.funding_organization', 'CHED'));
    $this->actingAs(User::factory()->coordinator()->create())
        ->get(route('coordinator.evaluations.show', $submission))->assertForbidden();
});

it('rejects invalid funding on create and update', function (array $funding, string $field) {
    [$faculty, $payload] = fundingFixture();
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();

    $this->post(route('faculty.submissions.store'), [...$payload, 'title' => 'Another Funding Test', ...$funding])
        ->assertSessionHasErrors($field);
    $this->put(route('faculty.submissions.update', $submission), [...$payload, ...$funding])
        ->assertSessionHasErrors($field);
    expect($submission->fresh()->funding_source)->toBeNull()
        ->and($submission->fresh()->funding_organization)->toBeNull();
    expect(ResearchSubmission::query()->count())->toBe(1);
})->with([
    'external requires an organization' => [['funding_source' => 'external'], 'funding_organization'],
    'external rejects blank organization' => [['funding_source' => 'external', 'funding_organization' => '   '], 'funding_organization'],
    'organization is bounded' => [['funding_source' => 'external', 'funding_organization' => str_repeat('a', 256)], 'funding_organization'],
    'personal rejects stale organization' => [['funding_source' => 'personal', 'funding_organization' => 'DOST'], 'funding_organization'],
    'internal rejects stale organization' => [['funding_source' => 'internal', 'funding_organization' => 'DOST'], 'funding_organization'],
    'unspecified rejects organization' => [['funding_organization' => 'DOST'], 'funding_organization'],
    'invalid source' => [['funding_source' => 'other'], 'funding_source'],
]);

it('does not let another faculty member update funding', function () {
    [$faculty, $payload] = fundingFixture();
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();

    $this->actingAs(User::factory()->faculty()->create())
        ->put(route('faculty.submissions.update', $submission), [...$payload, 'funding_source' => 'external', 'funding_organization' => 'DOST'])
        ->assertForbidden();

    expect($submission->fresh()->funding_source)->toBeNull();
});
