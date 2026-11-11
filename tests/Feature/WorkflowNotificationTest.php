<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Notifications\ResearchWorkflowNotification;
use App\Services\ResearchWorkflowService;
use Illuminate\Support\Facades\Notification;

it('prevents a submission participant from evaluating or approving their own research', function () {
    $college = College::query()->create(['code' => 'CON', 'name' => 'Conflict College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'CON', 'name' => 'Conflict Department']);
    $coordinator = User::factory()->coordinator()->create(['department_id' => $department->id]);
    $otherCoordinator = User::factory()->coordinator()->create(['department_id' => $department->id]);
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $year = AcademicYear::query()->create(['name' => 'Conflict year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $submission = ResearchSubmission::query()->create([
        'faculty_id' => $coordinator->id, 'department_id' => $department->id, 'college_id' => $college->id,
        'academic_year_id' => $year->id, 'title' => 'Conflict test research', 'research_type' => 'journal_article',
        'authors' => [$coordinator->name, $admin->name], 'status' => SubmissionStatus::Submitted,
    ]);
    $submission->authorshipAllocations()->create(['faculty_id' => $admin->id, 'name' => $admin->name, 'author_order' => 1, 'allocated_points' => 0]);

    expect($coordinator->can('evaluate', $submission))->toBeFalse()
        ->and($otherCoordinator->can('evaluate', $submission))->toBeTrue();
    $submission->update(['status' => SubmissionStatus::Evaluated]);
    expect($admin->can('approve', $submission))->toBeFalse()
        ->and($otherAdmin->can('approve', $submission))->toBeTrue();
});

it('notifies assigned coordinators when faculty submit research', function () {
    Notification::fake();
    $college = College::query()->create(['code' => 'NTF', 'name' => 'Notification College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'NTD', 'name' => 'Notification Department']);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $coordinator = User::factory()->coordinator()->create(['department_id' => $department->id]);
    $otherCoordinator = User::factory()->coordinator()->create();
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);
    $submission = ResearchSubmission::query()->create([
        'faculty_id' => $faculty->id,
        'college_id' => $college->id,
        'department_id' => $department->id,
        'academic_year_id' => $year->id,
        'title' => 'Workflow notification title',
        'research_type' => 'journal_article',
        'authors' => [$faculty->name],
        'status' => SubmissionStatus::Draft,
        'claimed_points' => 100,
    ]);

    app(ResearchWorkflowService::class)->submit($submission, $faculty);

    Notification::assertSentTo($coordinator, function (ResearchWorkflowNotification $notification) use ($coordinator, $submission): bool {
        return $notification->toArray($coordinator)['url'] === route('coordinator.evaluations.show', $submission->uuid);
    });
    Notification::assertNotSentTo($otherCoordinator, ResearchWorkflowNotification::class);
});
