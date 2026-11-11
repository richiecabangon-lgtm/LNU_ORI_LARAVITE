<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\User;
use App\Notifications\ResearchWorkflowNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function staffResearchFixture(string $role = 'coordinator'): array
{
    $college = College::query()->create(['code' => 'STF', 'name' => 'Staff College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'STF', 'name' => 'Staff Department']);
    $staff = User::factory()->{$role}()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $year = AcademicYear::query()->create(['name' => 'Staff year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $type = ResearchType::query()->create(['name' => 'Staff research', 'slug' => 'staff_research', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    ResearchTypePointSetting::query()->create(['research_type' => $type->slug, 'points_per_output' => 10]);
    $payload = ['title' => 'Staff personal study', 'academic_year_id' => $year->id, 'research_type' => $type->slug];

    return compact('college', 'department', 'staff', 'year', 'type', 'payload');
}

it('allows staff to manage their own draft and submit without changing their role', function (string $role) {
    Storage::fake('local');
    Notification::fake();
    $fixture = staffResearchFixture($role);
    $staff = $fixture['staff'];
    $reviewer = User::factory()->coordinator()->create(['department_id' => $fixture['department']->id]);
    $payload = $fixture['payload'];
    $payload['attachments'] = [UploadedFile::fake()->create('paper.pdf', 5, 'application/pdf')];

    $this->actingAs($staff)->get(route('faculty.submissions.create'))->assertOk();
    $this->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    expect($submission->faculty_id)->toBe($staff->id)
        ->and($staff->fresh()->role->value)->toBe($role)
        ->and($submission->authorshipAllocations()->firstOrFail()->faculty_id)->toBe($staff->id);
    $this->get(route('faculty.submissions.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1));
    $this->get(route('faculty.submissions.show', $submission))->assertOk();
    $this->get(route('faculty.submissions.edit', $submission))->assertOk();
    $this->get(route('submission-attachments.download', $submission->attachments()->firstOrFail()))->assertOk();
    $this->get(route('faculty.certificates.show', $submission))->assertForbidden();
    $payload = $fixture['payload'];
    $payload['title'] = 'Updated staff study';
    $this->put(route('faculty.submissions.update', $submission), $payload)->assertSessionHasNoErrors();
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Submitted);
    Notification::assertSentTo($reviewer, ResearchWorkflowNotification::class);
    Notification::assertNotSentTo($staff, ResearchWorkflowNotification::class);
    $this->get(route('research-profiles.personal'))->assertRedirect(route('research-profiles.show', $staff));
    $this->get(route('research-profiles.show', $staff))->assertOk();
})->with(['coordinator', 'admin']);

it('keeps staff drafts editable until a department is assigned', function (string $role) {
    Notification::fake();
    $fixture = staffResearchFixture($role);
    $staff = $fixture['staff'];
    $staff->update(['department_id' => null]);
    $this->actingAs($staff)->post(route('faculty.submissions.store'), $fixture['payload'])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasErrors('department');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Draft)->and($submission->statusHistories()->count())->toBe(0);
    $staff->update(['department_id' => $fixture['department']->id]);
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasNoErrors();
    expect($submission->fresh()->department_id)->toBe($fixture['department']->id);
})->with(['coordinator', 'admin']);

it('links staff coauthors and prevents participants from evaluating or approving through either endpoint', function () {
    Notification::fake();
    $fixture = staffResearchFixture();
    $fixture['type']->update(['has_authors' => true]);
    $owner = $fixture['staff'];
    $coauthor = User::factory()->coordinator()->create(['department_id' => $fixture['department']->id]);
    $reviewer = User::factory()->coordinator()->create(['department_id' => $fixture['department']->id]);
    $admin = User::factory()->admin()->create();
    $independentAdmin = User::factory()->admin()->create();
    $payload = $fixture['payload'] + [
        'authors' => [$owner->name, $coauthor->name, $admin->name],
        'author_details' => [
            ['name' => $owner->name, 'email' => $owner->email, 'allocated_points' => 4],
            ['name' => $coauthor->name, 'email' => $coauthor->email, 'allocated_points' => 3, 'authorization_file' => UploadedFile::fake()->create('one.pdf', 5, 'application/pdf')],
            ['name' => $admin->name, 'email' => $admin->email, 'allocated_points' => 3, 'authorization_file' => UploadedFile::fake()->create('two.pdf', 5, 'application/pdf')],
        ],
        'submit_now' => true,
    ];
    Storage::fake('local');
    $this->actingAs($owner)->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    expect($submission->authorshipAllocations()->where('faculty_id', $coauthor->id)->exists())->toBeTrue()
        ->and($submission->authorshipAllocations()->where('faculty_id', $admin->id)->exists())->toBeTrue();
    Notification::assertNotSentTo($owner, ResearchWorkflowNotification::class);
    Notification::assertNotSentTo($coauthor, ResearchWorkflowNotification::class);
    Notification::assertSentTo($reviewer, ResearchWorkflowNotification::class);

    foreach ([$owner, $coauthor] as $participant) {
        $this->actingAs($participant)->get(route('coordinator.evaluations.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
        $this->get(route('coordinator.evaluations.show', $submission))->assertForbidden();
        $this->post(route('coordinator.evaluations.store', $submission), ['recommendation' => 'recommend_approval', 'remarks' => 'Conflict'])->assertForbidden();
    }
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->statusHistories()->count())->toBe(1)
        ->and($submission->evaluation()->exists())->toBeFalse();
    $this->actingAs($reviewer)->get(route('coordinator.evaluations.show', $submission))->assertOk();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Submitted);
    $this->post(route('coordinator.evaluations.store', $submission), [
        'recommendation' => 'recommend_approval', 'remarks' => 'Independent', 'awarded_points' => 10,
        'score' => 80, 'quality_rating' => 4, 'relevance_rating' => 4, 'documentation_rating' => 4,
        'review_confirmed' => true,
    ])->assertSessionHasNoErrors();
    Notification::assertSentTo($admin, fn (ResearchWorkflowNotification $notification) => $notification->toArray($admin)['url'] === route('faculty.submissions.show', $submission));
    Notification::assertSentTo($independentAdmin, fn (ResearchWorkflowNotification $notification) => $notification->toArray($independentAdmin)['url'] === route('admin.approvals.show', $submission));
    $this->actingAs($admin)->get(route('admin.approvals.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
    $this->get(route('admin.approvals.show', $submission))->assertForbidden();
    $this->post(route('admin.approvals.store', $submission), ['decision' => 'approved'])->assertForbidden();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Evaluated)->and($submission->approval()->exists())->toBeFalse();
    $this->actingAs($independentAdmin)->get(route('admin.approvals.show', $submission))->assertOk();
    $this->post(route('admin.approvals.store', $submission), ['decision' => 'approved'])->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Approved);
});

it('separates personal reports from staff reporting scope and flags missing independent reviewers', function (string $role) {
    Notification::fake();
    $fixture = staffResearchFixture($role);
    $staff = $fixture['staff'];
    $this->actingAs($staff)->post(route('faculty.submissions.store'), $fixture['payload'] + ['submit_now' => true])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $other = User::factory()->faculty()->create(['college_id' => $fixture['college']->id, 'department_id' => $fixture['department']->id]);
    $otherSubmission = ResearchSubmission::query()->create([
        'faculty_id' => $other->id, 'department_id' => $fixture['department']->id, 'college_id' => $fixture['college']->id,
        'academic_year_id' => $fixture['year']->id, 'title' => 'Other research', 'research_type' => $fixture['type']->slug,
        'authors' => [$other->name], 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
    ]);
    $filters = ['period' => 'annual', 'year' => now()->year];
    $this->get(route('reports.personal', $filters))->assertInertia(fn (Assert $page) => $page
        ->where('scope', 'faculty')->where('personalReport', true)->has('submissions', 1)
        ->where('submissions.0.uuid', $submission->uuid)
        ->where('submissions.0.review_blocked', true));
    $this->get(route('reports.fpes', $filters))->assertInertia(fn (Assert $page) => $page->where('scope', $role)->has('submissions', 2));
    $this->get(route('faculty.submissions.show', $otherSubmission))->assertForbidden();
})->with(['coordinator', 'admin']);

it('allows eligible staff participants to obtain certificates but not unrelated staff', function (string $role) {
    $fixture = staffResearchFixture($role);
    $staff = $fixture['staff'];
    $this->actingAs($staff)->post(route('faculty.submissions.store'), $fixture['payload'])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $submission->update(['status' => SubmissionStatus::Approved, 'research_type' => 'published_research_published_individual_research_scopus_indexed_journal']);
    $coauthor = User::factory()->coordinator()->create(['department_id' => $fixture['department']->id]);
    $submission->authorshipAllocations()->create(['faculty_id' => $coauthor->id, 'name' => $coauthor->name, 'author_order' => 2, 'allocated_points' => 0]);
    $this->get(route('faculty.certificates.show', $submission))->assertOk();
    $this->get(route('faculty.certificates.download', $submission))->assertOk();
    $this->actingAs($coauthor)->get(route('faculty.certificates.show', $submission))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('faculty.certificates.show', $submission))->assertForbidden();
})->with(['coordinator', 'admin']);

it('restricts personal routes to active accounts and preserves review role boundaries', function () {
    $fixture = staffResearchFixture();
    $staff = $fixture['staff'];
    $faculty = User::factory()->faculty()->create();
    $inactive = User::factory()->admin()->create(['is_active' => false]);
    $this->actingAs($inactive)->get(route('faculty.submissions.index'))->assertForbidden();
    $this->get(route('reports.personal'))->assertForbidden();
    $this->get(route('research-profiles.personal'))->assertForbidden();
    $this->post(route('faculty.submissions.extract-metadata'))->assertForbidden();
    $this->actingAs($staff)->post(route('faculty.submissions.extract-metadata'))->assertSessionHasErrors('paper');
    $this->actingAs($faculty)->get(route('admin.approvals.index'))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertForbidden();
});

it('treats unlinked legacy authors as conflicts without granting personal access', function () {
    Notification::fake();
    $fixture = staffResearchFixture();
    $owner = User::factory()->faculty()->create(['department_id' => $fixture['department']->id]);
    $coordinator = $fixture['staff'];
    $admin = User::factory()->admin()->create();
    $this->actingAs($owner)->post(route('faculty.submissions.store'), $fixture['payload'])->assertSessionHasNoErrors();
    $submission = ResearchSubmission::query()->where('title', $fixture['payload']['title'])->firstOrFail();
    $submission->authorshipAllocations()->create(['name' => $coordinator->name, 'email' => $coordinator->email, 'author_order' => 2, 'allocated_points' => 0]);
    $submission->authorshipAllocations()->create(['name' => $admin->name, 'author_order' => 3, 'allocated_points' => 0]);
    $this->post(route('faculty.submissions.submit', $submission))->assertSessionHasNoErrors();
    Notification::assertNotSentTo($coordinator, ResearchWorkflowNotification::class);
    $this->actingAs($coordinator)->get(route('faculty.submissions.show', $submission))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
    $this->get(route('coordinator.evaluations.show', $submission))->assertForbidden();
    $this->post(route('coordinator.evaluations.store', $submission), [])->assertForbidden();
    $submission->update(['status' => SubmissionStatus::Evaluated]);
    $this->actingAs($admin)->get(route('faculty.submissions.show', $submission))->assertForbidden();
    $this->get(route('admin.approvals.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
    $this->get(route('admin.approvals.show', $submission))->assertForbidden();
    $this->post(route('admin.approvals.store', $submission), [])->assertForbidden();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Evaluated)->and($submission->approval()->exists())->toBeFalse();
});
