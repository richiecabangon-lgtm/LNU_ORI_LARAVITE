<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function interestsFixture(): array
{
    $college = College::query()->create(['code' => 'INT', 'name' => 'Interests College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'INT', 'name' => 'Interests Department']);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'is_current' => true, 'is_active' => true,
    ]);
    ResearchType::withTrashed()->where('slug', 'journal_article')->update(['is_active' => true, 'deleted_at' => null]);
    ResearchTypePointSetting::query()->updateOrCreate(['research_type' => 'journal_article'], ['points_per_output' => 100]);

    return [$faculty, $department, [
        'title' => 'Research interests test',
        'research_type' => 'journal_article',
        'academic_year_id' => $year->id,
        'authors' => [$faculty->name],
        'author_details' => [['name' => $faculty->name, 'email' => $faculty->email, 'allocated_points' => 100]],
        'submit_now' => false,
    ]];
}

function interestsCsvRows(string $content): array
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

it('updates only the authenticated profile and supports omitted and cleared interests', function () {
    $faculty = User::factory()->faculty()->create(['research_interests' => ['Existing']]);
    $other = User::factory()->faculty()->create(['research_interests' => ['Private']]);
    $profile = ['name' => $faculty->name, 'email' => $faculty->email];

    $this->patch(route('profile.update'), $profile)->assertRedirect(route('login'));
    $this->actingAs($faculty)->patch(route('profile.update'), [...$profile, 'id' => $other->id, 'research_interests' => ['Language', 'Culture']])->assertSessionHasNoErrors();
    expect($faculty->fresh()->research_interests)->toBe(['Language', 'Culture']);
    expect($other->fresh()->research_interests)->toBe(['Private']);
    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('user.research_interests', ['Language', 'Culture']));

    $this->patch(route('profile.update'), $profile)->assertSessionHasNoErrors();
    expect($faculty->fresh()->research_interests)->toBe(['Language', 'Culture']);
    $this->patch(route('profile.update'), [...$profile, 'research_interests' => []])->assertSessionHasNoErrors();
    expect($faculty->fresh()->research_interests)->toBe([]);
});

it('rejects invalid profile interests', function (mixed $interests, string $error) {
    $faculty = User::factory()->faculty()->create(['research_interests' => ['Original']]);
    $this->actingAs($faculty)->patch(route('profile.update'), [
        'name' => $faculty->name, 'email' => $faculty->email, 'research_interests' => $interests,
    ])->assertSessionHasErrors($error);
    expect($faculty->fresh()->research_interests)->toBe(['Original']);
})->with([
    'six entries' => [array_fill(0, 6, 'Topic'), 'research_interests'],
    'too long' => [[str_repeat('a', 256)], 'research_interests.0'],
    'blank' => [['   '], 'research_interests.0'],
    'not an array' => ['Topic', 'research_interests'],
    'associative array' => [['subject' => 'Topic'], 'research_interests'],
    'not a string' => [[123], 'research_interests.0'],
]);

it('prefills from the current profile without exposing other faculty interests', function () {
    [$faculty] = interestsFixture();
    $faculty->update(['research_interests' => ['Language', 'Culture']]);
    User::factory()->faculty()->create(['research_interests' => ['Private topic']]);

    $this->actingAs($faculty)->get(route('faculty.submissions.create'))->assertInertia(fn (Assert $page) => $page
        ->where('currentFaculty.research_interests', ['Language', 'Culture'])
        ->where('facultyDirectory', fn ($directory) => $directory->every(fn ($person) => ! array_key_exists('research_interests', $person))));
    expect($faculty->fresh()->toArray())->not->toHaveKey('research_interests');
});

it('shows research interests only to viewers authorized for the faculty profile', function () {
    [$faculty, $department] = interestsFixture();
    $faculty->update(['research_interests' => ['Language', 'Culture']]);

    $this->actingAs($faculty)->get(route('research-profiles.show', $faculty))
        ->assertInertia(fn (Assert $page) => $page->where('faculty.research_interests', ['Language', 'Culture']));
    $this->actingAs(User::factory()->coordinator()->create(['department_id' => $department->id]))
        ->get(route('research-profiles.show', $faculty))
        ->assertInertia(fn (Assert $page) => $page->where('faculty.research_interests', ['Language', 'Culture']));
    $this->actingAs(User::factory()->faculty()->create())->get(route('research-profiles.show', $faculty))->assertForbidden();
});

it('stores editable snapshots independently of profile changes and legacy updates', function () {
    [$faculty, $department, $payload] = interestsFixture();
    $faculty->update(['research_interests' => ['Original profile']]);
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), [...$payload, 'research_interests' => ['Manual choice']])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();
    $faculty->update(['research_interests' => ['Changed profile']]);

    $this->get(route('faculty.submissions.edit', $submission))->assertInertia(fn (Assert $page) => $page
        ->where('submission.research_interests', ['Manual choice'])
        ->where('currentFaculty.research_interests', ['Changed profile']));
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->research_interests)->toBe(['Manual choice']);
    $this->put(route('faculty.submissions.update', $submission), [...$payload, 'research_interests' => ['Edited snapshot']])->assertSessionHasNoErrors();
    expect($submission->fresh()->research_interests)->toBe(['Edited snapshot']);
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.research_interests', ['Edited snapshot']));
    $this->actingAs(User::factory()->coordinator()->create(['department_id' => $department->id]))
        ->get(route('coordinator.evaluations.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.research_interests', ['Edited snapshot']));
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.approvals.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.research_interests', ['Edited snapshot']));
    $this->actingAs(User::factory()->coordinator()->create())->get(route('coordinator.evaluations.show', $submission))->assertForbidden();
    $this->actingAs(User::factory()->faculty()->create())->put(route('faculty.submissions.update', $submission), [...$payload, 'research_interests' => ['Unauthorized']])->assertForbidden();
    expect($submission->fresh()->research_interests)->toBe(['Edited snapshot']);
    $this->actingAs($faculty)->put(route('faculty.submissions.update', $submission), [...$payload, 'research_interests' => null])->assertSessionHasNoErrors();
    expect($submission->fresh()->research_interests)->toBeNull();
});

it('keeps legacy submissions null and validates interest limits on create and update', function (mixed $interests, string $error) {
    [$faculty, $department, $payload] = interestsFixture();
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();
    expect($submission->research_interests)->toBeNull();
    $this->post(route('faculty.submissions.store'), [...$payload, 'title' => 'Another interest test', 'research_interests' => $interests])->assertSessionHasErrors($error);
    $this->put(route('faculty.submissions.update', $submission), [...$payload, 'research_interests' => $interests])->assertSessionHasErrors($error);
    expect($submission->fresh()->research_interests)->toBeNull();
})->with([
    'six entries' => [array_fill(0, 6, 'Topic'), 'research_interests'],
    'too long' => [[str_repeat('a', 256)], 'research_interests.0'],
    'blank' => [['   '], 'research_interests.0'],
    'invalid type' => [[['nested']], 'research_interests.0'],
    'associative array' => [['subject' => 'Topic'], 'research_interests'],
]);

it('accepts five interests of up to 255 characters', function () {
    [$faculty, $department, $payload] = interestsFixture();
    $interests = [str_repeat('a', 255), 'Two', 'Three', 'Four', 'Five'];
    $this->actingAs($faculty)->patch(route('profile.update'), ['name' => $faculty->name, 'email' => $faculty->email, 'research_interests' => $interests])->assertSessionHasNoErrors();
    $this->post(route('faculty.submissions.store'), [...$payload, 'research_interests' => $interests])->assertSessionHasNoErrors();
    expect($faculty->fresh()->research_interests)->toBe($interests)
        ->and(ResearchSubmission::query()->firstOrFail()->research_interests)->toBe($interests);
});

it('exports selected snapshot interests for the filtered report and protects spreadsheet cells', function () {
    [$faculty, $department, $payload] = interestsFixture();
    $faculty->update(['research_interests' => ['Current profile']]);
    $this->actingAs($faculty)->post(route('faculty.submissions.store'), [...$payload, 'research_interests' => ['=formula', 'Culture']])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->firstOrFail();
    $submission->update(['status' => SubmissionStatus::Submitted, 'publication_date' => '2026-10-05']);
    $filters = ['period' => 'monthly', 'year' => 2026, 'month' => 10, 'department_id' => $department->id];
    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('exportColumns.interests', 'Interests')
        ->where('submissions', fn ($records) => $records->count() === 1 && $records->first()['id'] === $submission->id));
    $response = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference', 'interests']]))->assertOk();
    expect(interestsCsvRows($response->streamedContent()))->toBe([['Reference', 'Interests'], [$submission->reference_code, "'=formula, Culture"]]);
    $response = $this->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['reference']]))->assertOk();
    expect(interestsCsvRows($response->streamedContent()))->toBe([['Reference'], [$submission->reference_code]]);
    $this->actingAs($faculty)->get(route('admin.reports.fpes.export', [...$filters, 'columns' => ['interests']]))->assertForbidden();
});
