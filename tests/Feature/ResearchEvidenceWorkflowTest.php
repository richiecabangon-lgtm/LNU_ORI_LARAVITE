<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function evidenceFixture(): array
{
    $faculty = User::factory()->faculty()->create();
    $year = AcademicYear::query()->create(['name' => 'Evidence year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $type = ResearchType::query()->create(['name' => 'Evidence type', 'slug' => 'evidence_type', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    ResearchTypePointSetting::query()->create(['research_type' => $type->slug, 'points_per_output' => 10]);
    $requirement = $type->evidenceRequirements()->create(['name' => 'Research paper', 'is_required' => true]);
    $optional = $type->evidenceRequirements()->create(['name' => 'Additional evidence', 'is_required' => false]);
    $payload = ['title' => 'Evidence submission', 'research_type' => $type->slug, 'academic_year_id' => $year->id, 'keywords' => 'kept', 'submit_now' => false];

    return compact('faculty', 'year', 'type', 'requirement', 'optional', 'payload');
}

function evidenceTypePayload(ResearchType $type, array $requirements): array
{
    return ['name' => $type->name, 'points' => 10, 'is_active' => true, 'has_authors' => false, 'requirements' => $requirements];
}

it('stores multiple activity dates independently of the legacy date and claim', function () {
    $fixture = evidenceFixture();
    $payload = $fixture['payload'] + [
        'publication_date' => '2026-10-05',
        'activity_dates' => ['mode' => 'multiple', 'dates' => ['2026-11-18', '2026-11-04', '2026-11-11']],
    ];

    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();

    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    expect($submission->activity_dates)->toBe(['mode' => 'multiple', 'dates' => ['2026-11-04', '2026-11-11', '2026-11-18']])
        ->and($submission->publication_date->format('Y-m-d'))->toBe('2026-10-05')
        ->and((float) $submission->claimed_points)->toBe(10.0);
});

it('updates, retains and explicitly clears activity dates without changing the legacy date', function () {
    $fixture = evidenceFixture();
    $payload = $fixture['payload'] + ['publication_date' => '2026-10-05', 'activity_dates' => ['mode' => 'single', 'date' => '2026-11-04']];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    expect($submission->activity_dates)->toBe(['mode' => 'single', 'date' => '2026-11-04']);

    $payload['activity_dates'] = ['mode' => 'range', 'start' => '2026-11-10', 'end' => '2026-11-15'];
    unset($payload['publication_date']);
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->activity_dates)->toBe($payload['activity_dates'])
        ->and($submission->fresh()->publication_date->format('Y-m-d'))->toBe('2026-10-05');

    unset($payload['activity_dates']);
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->activity_dates)->toBe(['mode' => 'range', 'start' => '2026-11-10', 'end' => '2026-11-15']);
    $payload['activity_dates'] = null;
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->activity_dates)->toBeNull()->and($submission->fresh()->publication_date->format('Y-m-d'))->toBe('2026-10-05');
    $payload['activity_dates'] = ['mode' => 'single', 'date' => '2026-11-04'];
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    $payload['activity_dates'] = '';
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->activity_dates)->toBeNull();
});

it('does not derive quantity or points from the number of activity dates', function () {
    $fixture = evidenceFixture();
    $fixture['type']->update(['unit' => 'day']);
    $dates = ['mode' => 'multiple', 'dates' => ['2026-11-04', '2026-11-11', '2026-11-18']];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), [...$fixture['payload'], 'quantity' => 2, 'activity_dates' => $dates])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    expect((float) $submission->quantity)->toBe(2.0)->and((float) $submission->claimed_points)->toBe(20.0)->and($submission->activity_dates)->toBe($dates);
    $this->put(route('faculty.submissions.update', $submission), [...$fixture['payload'], 'quantity' => 2, 'activity_dates' => ['mode' => 'range', 'start' => '2026-11-10', 'end' => '2026-11-15']])->assertSessionHasNoErrors();
    expect((float) $submission->fresh()->quantity)->toBe(2.0)->and((float) $submission->fresh()->claimed_points)->toBe(20.0);
});

it('rejects invalid activity date shapes on creation and update', function (mixed $dates, string $error) {
    $fixture = evidenceFixture();
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $fixture['payload'])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $payload = [...$fixture['payload'], 'title' => 'Invalid activity', 'activity_dates' => $dates];
    $this->postJson(route('faculty.submissions.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $payload['title'] = $submission->title;
    $this->putJson(route('faculty.submissions.update', $submission), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($submission->fresh()->activity_dates)->toBeNull();
})->with([
    'empty' => [[], 'activity_dates'],
    'not an object' => ['invalid', 'activity_dates'],
    'missing mode' => [['date' => '2026-11-04'], 'activity_dates'],
    'invalid mode' => [['mode' => 'weekly'], 'activity_dates.mode'],
    'missing single' => [['mode' => 'single'], 'activity_dates.date'],
    'invalid date' => [['mode' => 'single', 'date' => '2026-02-30'], 'activity_dates.date'],
    'non ISO' => [['mode' => 'single', 'date' => '2026-1-04'], 'activity_dates.date'],
    'reverse range' => [['mode' => 'range', 'start' => '2026-11-15', 'end' => '2026-11-10'], 'activity_dates.end'],
    'missing range end' => [['mode' => 'range', 'start' => '2026-11-10'], 'activity_dates.end'],
    'duplicate' => [['mode' => 'multiple', 'dates' => ['2026-11-04', '2026-11-04']], 'activity_dates.dates.0'],
    'one multiple date' => [['mode' => 'multiple', 'dates' => ['2026-11-04']], 'activity_dates.dates'],
    'too many dates' => [['mode' => 'multiple', 'dates' => array_fill(0, 51, '2026-11-04')], 'activity_dates.dates'],
    'non-list dates' => [['mode' => 'multiple', 'dates' => ['first' => '2026-11-04', 'second' => '2026-11-11']], 'activity_dates.dates'],
    'unknown key' => [['mode' => 'single', 'date' => '2026-11-04', 'end' => '2026-11-05'], 'activity_dates'],
]);

it('restricts activity dates to role-based types and preserves them when changing type', function () {
    $fixture = evidenceFixture();
    $authored = ResearchType::query()->create(['name' => 'Authored work', 'slug' => 'authored_work', 'is_active' => true, 'has_authors' => true, 'sort_order' => 999]);
    ResearchTypePointSetting::query()->create(['research_type' => $authored->slug, 'points_per_output' => 10]);
    $dates = ['mode' => 'single', 'date' => '2026-11-04'];
    $payload = [...$fixture['payload'], 'publication_date' => '2026-10-05', 'activity_dates' => $dates];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $payload['research_type'] = $authored->slug;
    $payload['authors'] = [$fixture['faculty']->name];
    $payload['author_details'] = [['name' => $fixture['faculty']->name, 'email' => $fixture['faculty']->email, 'allocated_points' => 10]];
    $this->putJson(route('faculty.submissions.update', $submission), $payload)->assertUnprocessable()->assertJsonValidationErrors('activity_dates');
    $payload['activity_dates'] = null;
    $this->putJson(route('faculty.submissions.update', $submission), $payload)->assertUnprocessable()->assertJsonValidationErrors('activity_dates');
    unset($payload['activity_dates']);
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->activity_dates)->toBe($dates)->and($submission->fresh()->publication_date->format('Y-m-d'))->toBe('2026-10-05');
    $payload['title'] = 'Authored invalid';
    $payload['activity_dates'] = $dates;
    $this->postJson(route('faculty.submissions.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('activity_dates');
});

it('exposes activity dates only through authorized submission views', function () {
    $fixture = evidenceFixture();
    $college = College::query()->create(['code' => 'DATES', 'name' => 'Dates College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'DATES', 'name' => 'Dates Department']);
    $fixture['faculty']->update(['college_id' => $college->id, 'department_id' => $department->id]);
    $coordinator = User::factory()->coordinator()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $admin = User::factory()->admin()->create();
    $outsider = User::factory()->faculty()->create();
    $dates = ['mode' => 'range', 'start' => '2026-11-10', 'end' => '2026-11-15'];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), [...$fixture['payload'], 'activity_dates' => $dates])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $this->get(route('faculty.submissions.edit', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.activity_dates', $dates));
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.activity_dates', $dates));
    $this->actingAs($outsider)->get(route('faculty.submissions.show', $submission))->assertForbidden();
    $this->put(route('faculty.submissions.update', $submission), [...$fixture['payload'], 'activity_dates' => null])->assertForbidden();
    $this->actingAs(User::factory()->coordinator()->create())->get(route('coordinator.evaluations.show', $submission))->assertForbidden();
    $this->actingAs($coordinator)->get(route('coordinator.evaluations.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.activity_dates', $dates));
    $this->actingAs($admin)->get(route('admin.approvals.show', $submission))->assertInertia(fn (Assert $page) => $page->where('submission.activity_dates', $dates));
});

it('restricts configuration to admins and preserves requirement identities and history', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $admin = User::factory()->admin()->create();
    $changes = [['id' => $fixture['requirement']->id, 'name' => 'Revised paper', 'is_required' => true]];
    $this->actingAs($fixture['faculty'])->put(route('admin.research-types.update', $fixture['type']->id), evidenceTypePayload($fixture['type'], $changes))->assertForbidden();
    $payload = $fixture['payload'] + ['attachments' => [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')], 'attachment_requirements' => [$fixture['requirement']->id]];
    $payload['submit_now'] = true;
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $snapshot = $submission->statusHistories()->where('action', 'submitted')->firstOrFail()->meta['evidence_requirements'];
    expect($snapshot[0]['name'])->toBe('Research paper')->and($snapshot[0]['attachments'][0]['name'])->toBe('paper.pdf')->and($snapshot[1]['is_required'])->toBeFalse();
    $this->actingAs($admin)->put(route('admin.research-types.update', $fixture['type']->id), evidenceTypePayload($fixture['type'], $changes))->assertSessionHasNoErrors();
    expect($fixture['requirement']->fresh()->name)->toBe('Revised paper')->and($fixture['optional']->fresh()->is_active)->toBeFalse()
        ->and($submission->statusHistories()->where('action', 'submitted')->firstOrFail()->meta['evidence_requirements'])->toBe($snapshot);
});

it('saves incomplete drafts but rejects unlabelled files on all submission paths without leaking files', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $payload = $fixture['payload'];
    $payload['submit_now'] = true;
    $payload['attachments'] = [UploadedFile::fake()->create('unlabelled.pdf', 5, 'application/pdf')];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasErrors('evidence');
    expect(ResearchSubmission::query()->where('title', $payload['title'])->exists())->toBeFalse()->and(Storage::disk('local')->allFiles('research-attachments'))->toBeEmpty();

    $payload['submit_now'] = false;
    $payload['attachments'] = [UploadedFile::fake()->create('draft.pdf', 5, 'application/pdf')];
    $this->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasErrors('evidence');
    $payload['submit_now'] = true;
    $payload['attachments'] = [UploadedFile::fake()->create('second.pdf', 5, 'application/pdf')];
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasErrors('evidence');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Draft)->and($submission->attachments()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('research-attachments'))->toHaveCount(1);
});

it('matches saved files explicitly and validates resubmissions after deletion', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $payload = $fixture['payload'];
    $payload['attachments'] = [UploadedFile::fake()->create('draft.pdf', 5, 'application/pdf')];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $attachment = $submission->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    $this->actingAs(User::factory()->faculty()->create())->get(route('submission-attachments.download', $attachment))->assertForbidden();
    $payload = $fixture['payload'];
    $payload['existing_attachment_requirements'] = [$attachment->id => $fixture['requirement']->id];
    $payload['submit_now'] = true;
    $this->actingAs($fixture['faculty'])->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Submitted);
    $submission->update(['status' => SubmissionStatus::RevisionRequested]);
    $this->delete(route('submission-attachments.destroy', $attachment))->assertRedirect();
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasErrors('evidence');
    Storage::disk('local')->assertMissing($attachment->path);
});

it('rejects requirements and attachment mappings from another type or submission', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $other = ResearchType::query()->create(['name' => 'Other evidence', 'slug' => 'other_evidence', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    ResearchTypePointSetting::query()->create(['research_type' => $other->slug, 'points_per_output' => 10]);
    $otherRequirement = $other->evidenceRequirements()->create(['name' => 'Other proof', 'is_required' => true]);
    $payload = $fixture['payload'];
    $payload['attachments'] = [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')];
    $payload['attachment_requirements'] = [$otherRequirement->id];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasErrors('attachment_requirements.0');
    $payload['attachment_requirements'] = [$fixture['requirement']->id];
    $payload['attachments'] = [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')];
    $this->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $update = $fixture['payload'];
    $update['existing_attachment_requirements'] = [999999 => $fixture['requirement']->id];
    $this->put(route('faculty.submissions.update', $submission), $update)->assertSessionHasErrors('evidence');
    $update = $fixture['payload'];
    $update['research_type'] = $other->slug;
    $update['submit_now'] = true;
    $this->put(route('faculty.submissions.update', $submission), $update)->assertSessionHasErrors('evidence');
    expect($submission->fresh()->research_type)->toBe($fixture['type']->slug)->and($submission->attachments()->firstOrFail()->research_evidence_requirement_id)->toBe($fixture['requirement']->id);
});

it('retains old author proof and removes new proof when immediate submission fails', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $fixture['type']->update(['has_authors' => true]);
    $coauthor = User::factory()->faculty()->create();
    $payload = $fixture['payload'];
    $payload['authors'] = [$fixture['faculty']->name, $coauthor->name];
    $payload['author_details'] = [
        ['name' => $fixture['faculty']->name, 'email' => $fixture['faculty']->email, 'allocated_points' => 6],
        ['name' => $coauthor->name, 'email' => $coauthor->email, 'allocated_points' => 4, 'authorization_file' => UploadedFile::fake()->create('old.pdf', 5, 'application/pdf')],
    ];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $oldPath = $submission->authorshipAllocations()->where('email', $coauthor->email)->firstOrFail()->authorization_path;
    $payload['author_details'][1]['authorization_file'] = UploadedFile::fake()->create('replacement.pdf', 5, 'application/pdf');
    $payload['submit_now'] = true;
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasErrors('evidence');
    Storage::disk('local')->assertExists($oldPath);
    expect(Storage::disk('local')->allFiles('research-authorizations'))->toHaveCount(1)
        ->and($submission->authorshipAllocations()->where('email', $coauthor->email)->firstOrFail()->authorization_path)->toBe($oldPath);
});

it('captures a separate snapshot on resubmission after requirements change', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $payload = $fixture['payload'];
    $payload['submit_now'] = true;
    $payload['attachments'] = [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')];
    $payload['attachment_requirements'] = [$fixture['requirement']->id];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $submission->update(['status' => SubmissionStatus::RevisionRequested]);
    $fixture['requirement']->update(['name' => 'Updated paper']);
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasNoErrors();
    $snapshots = $submission->statusHistories()->where('action', 'submitted')->orderBy('id')->get()->pluck('meta');
    expect($snapshots)->toHaveCount(2)->and($snapshots[0]['evidence_requirements'][0]['name'])->toBe('Research paper')
        ->and($snapshots[1]['evidence_requirements'][0]['name'])->toBe('Updated paper');
});

it('allows administrators to add requirements but rejects IDs belonging to another type', function () {
    $fixture = evidenceFixture();
    $other = ResearchType::query()->create(['name' => 'Another type', 'slug' => 'another_type', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    $otherRequirement = $other->evidenceRequirements()->create(['name' => 'Other proof', 'is_required' => true]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('admin.research-types.update', $fixture['type']->id), evidenceTypePayload($fixture['type'], [
        ['id' => $otherRequirement->id, 'name' => 'Hijacked proof', 'is_required' => true],
    ]))->assertSessionHasErrors('requirements');
    expect($otherRequirement->fresh()->name)->toBe('Other proof')->and($fixture['requirement']->fresh()->is_active)->toBeTrue();
    $this->put(route('admin.research-types.update', $fixture['type']->id), evidenceTypePayload($fixture['type'], [
        ['id' => $fixture['requirement']->id, 'name' => 'Research paper', 'is_required' => true],
        ['name' => 'New optional proof', 'is_required' => false],
    ]))->assertSessionHasNoErrors();
    expect($fixture['type']->evidenceRequirements()->where('name', 'New optional proof')->firstOrFail()->is_required)->toBeFalse();
});

it('clears old evidence labels when a draft changes type', function () {
    Storage::fake('local');
    $fixture = evidenceFixture();
    $other = ResearchType::query()->create(['name' => 'New type', 'slug' => 'new_type', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    ResearchTypePointSetting::query()->create(['research_type' => $other->slug, 'points_per_output' => 10]);
    $payload = $fixture['payload'];
    $payload['attachments'] = [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')];
    $payload['attachment_requirements'] = [$fixture['requirement']->id];
    $this->actingAs($fixture['faculty'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $update = $fixture['payload'];
    $update['research_type'] = $other->slug;
    $this->put(route('faculty.submissions.update', $submission), $update)->assertSessionHasNoErrors();
    expect($submission->fresh()->research_type)->toBe($other->slug)
        ->and($submission->attachments()->firstOrFail()->research_evidence_requirement_id)->toBeNull()
        ->and($submission->fresh()->keywords)->toBe('kept');
});
