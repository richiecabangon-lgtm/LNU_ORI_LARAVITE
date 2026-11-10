<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
