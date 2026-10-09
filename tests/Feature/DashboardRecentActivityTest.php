<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\Approval;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchSubmissionAuthor;
use App\Models\StatusHistory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows admin approval and rejection decisions in recent activity', function () {
    $admin = User::factory()->admin()->create(['name' => 'ORI Administrator']);
    $faculty = User::factory()->faculty()->create(['name' => 'Faculty Member']);
    $academicYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);

    foreach ([
        ['decision' => 'approved', 'status' => SubmissionStatus::Approved, 'title' => 'Approved research'],
        ['decision' => 'rejected', 'status' => SubmissionStatus::Rejected, 'title' => 'Rejected research'],
    ] as $index => $activity) {
        $submission = ResearchSubmission::query()->create([
            'faculty_id' => $faculty->id,
            'academic_year_id' => $academicYear->id,
            'title' => $activity['title'],
            'research_type' => 'journal_article',
            'authors' => [$faculty->name],
            'status' => $activity['status'],
            'claimed_points' => 100,
        ]);

        $history = StatusHistory::query()->create([
            'research_submission_id' => $submission->id,
            'user_id' => $admin->id,
            'from_status' => SubmissionStatus::Evaluated,
            'to_status' => $activity['status'],
            'action' => $activity['decision'],
        ]);

        $history->forceFill([
            'created_at' => now()->addSeconds($index),
            'updated_at' => now()->addSeconds($index),
        ])->saveQuietly();
    }

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recent', 2)
            ->where('recent.0.action', 'rejected')
            ->where('recent.0.actor', 'ORI Administrator')
            ->where('recent.0.status', 'rejected')
            ->where('recent.1.action', 'approved'));
});

it('ranks active faculty by approved allocated points at college and institution scopes', function () {
    $collegeOne = College::query()->create(['code' => 'LB1', 'name' => 'Leaderboard College One']);
    $collegeTwo = College::query()->create(['code' => 'LB2', 'name' => 'Leaderboard College Two']);
    $departmentOne = Department::query()->create(['college_id' => $collegeOne->id, 'code' => 'LBD1', 'name' => 'Leaderboard Department One']);
    $departmentTwo = Department::query()->create(['college_id' => $collegeTwo->id, 'code' => 'LBD2', 'name' => 'Leaderboard Department Two']);
    $academicYear = AcademicYear::query()->create([
        'name' => '2027-2028',
        'starts_on' => '2027-08-01',
        'ends_on' => '2028-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);
    $facultyOne = User::factory()->faculty()->create(['name' => 'Allocated Lead', 'college_id' => $collegeOne->id, 'department_id' => $departmentOne->id]);
    $facultyTwo = User::factory()->faculty()->create(['name' => 'Allocated Coauthor', 'college_id' => $collegeOne->id, 'department_id' => $departmentOne->id]);
    $facultyThree = User::factory()->faculty()->create(['name' => 'Institution Leader', 'college_id' => $collegeTwo->id, 'department_id' => $departmentTwo->id]);
    $starterFaculty = User::factory()->faculty()->create(['name' => 'Emerging Scholar', 'college_id' => $collegeOne->id, 'department_id' => $departmentOne->id]);
    $admin = User::factory()->admin()->create();

    $sharedSubmission = ResearchSubmission::query()->create([
        'faculty_id' => $facultyOne->id,
        'college_id' => $collegeOne->id,
        'department_id' => $departmentOne->id,
        'academic_year_id' => $academicYear->id,
        'title' => 'Shared approved research',
        'research_type' => 'journal_article',
        'authors' => [$facultyOne->name, $facultyTwo->name],
        'status' => SubmissionStatus::Approved,
        'claimed_points' => 100,
    ]);
    Approval::query()->create([
        'research_submission_id' => $sharedSubmission->id,
        'admin_id' => $admin->id,
        'decision' => 'approved',
        'final_points' => 100,
        'decided_at' => now(),
    ]);
    ResearchSubmissionAuthor::query()->create([
        'research_submission_id' => $sharedSubmission->id,
        'faculty_id' => $facultyOne->id,
        'name' => $facultyOne->name,
        'author_order' => 1,
        'allocated_points' => 60,
    ]);
    ResearchSubmissionAuthor::query()->create([
        'research_submission_id' => $sharedSubmission->id,
        'faculty_id' => $facultyTwo->id,
        'name' => $facultyTwo->name,
        'author_order' => 2,
        'allocated_points' => 40,
    ]);

    $institutionSubmission = ResearchSubmission::query()->create([
        'faculty_id' => $facultyThree->id,
        'college_id' => $collegeTwo->id,
        'department_id' => $departmentTwo->id,
        'academic_year_id' => $academicYear->id,
        'title' => 'Institution-leading approved research',
        'research_type' => 'journal_article',
        'authors' => [$facultyThree->name],
        'status' => SubmissionStatus::Approved,
        'claimed_points' => 150,
    ]);
    Approval::query()->create([
        'research_submission_id' => $institutionSubmission->id,
        'admin_id' => $admin->id,
        'decision' => 'approved',
        'final_points' => 150,
        'decided_at' => now(),
    ]);

    $rejectedSubmission = ResearchSubmission::query()->create([
        'faculty_id' => $facultyOne->id,
        'college_id' => $collegeOne->id,
        'department_id' => $departmentOne->id,
        'academic_year_id' => $academicYear->id,
        'title' => 'Rejected research excluded from ranking',
        'research_type' => 'journal_article',
        'authors' => [$facultyOne->name],
        'status' => SubmissionStatus::Rejected,
        'claimed_points' => 999,
    ]);
    Approval::query()->create([
        'research_submission_id' => $rejectedSubmission->id,
        'admin_id' => $admin->id,
        'decision' => 'rejected',
        'final_points' => 999,
        'decided_at' => now(),
    ]);

    expect(ResearchSubmission::query()->where('status', SubmissionStatus::Approved)->count())->toBe(2)
        ->and(Approval::query()->where('decision', 'approved')->count())->toBe(2);

    $this->actingAs($admin)
        ->get(route('dashboard', ['leaderboard_scope' => 'institution']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.items.0.name', 'Institution Leader')
            ->where('leaderboard.items.0.points', 150)
            ->where('leaderboard.items.0.achievement_tier.title', 'Developing Scholar')
            ->where('leaderboard.tiers.0.title', 'Emerging Scholar')
            ->where('leaderboard.tiers.1.minimum', 150)
            ->where('leaderboard.items.1.name', 'Allocated Lead')
            ->where('leaderboard.items.1.points', 60)
            ->where('leaderboard.items.2.name', 'Allocated Coauthor')
            ->where('leaderboard.items.2.points', 40)
            ->where('leaderboard.items', fn ($items) => collect($items)->every(fn ($item) => $item['name'] !== 'Rejected research excluded from ranking')));

    $this->get(route('dashboard', ['leaderboard_scope' => 'college', 'leaderboard_college_id' => $collegeOne->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.scope', 'college')
            ->where('leaderboard.items.0.name', 'Allocated Lead')
            ->where('leaderboard.items.1.name', 'Allocated Coauthor')
            ->has('leaderboard.items', 2));

    $this->actingAs($facultyTwo)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.scope', 'self')
            ->where('leaderboard.myStanding.college.rank', 2)
            ->where('leaderboard.myStanding.college.points', 40)
            ->where('leaderboard.myStanding.institution.rank', 3)
            ->where('leaderboard.myStanding.institution.points', 40)
            ->has('leaderboard.items', 0));

    $coordinator = User::factory()->coordinator()->create([
        'college_id' => $collegeOne->id,
        'department_id' => $departmentOne->id,
    ]);

    $this->actingAs($coordinator)
        ->get(route('dashboard', ['leaderboard_scope' => 'college', 'leaderboard_college_id' => $collegeTwo->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.scope', 'college')
            ->where('leaderboard.college_id', $collegeOne->id)
            ->has('leaderboard.items', 2)
            ->where('leaderboard.items.0.college_id', $collegeOne->id));
});
