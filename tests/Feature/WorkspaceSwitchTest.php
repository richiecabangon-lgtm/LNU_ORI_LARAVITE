<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('switches active staff workspaces and persists the selection without changing roles', function (string $role) {
    $staff = User::factory()->{$role}()->create();

    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty'])
        ->assertRedirect(route('faculty.submissions.index'));
    $this->get(route('faculty.submissions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('workspace', 'faculty')->where('auth.user.role', $role));
    $this->get(route('news.index'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty'));
    $this->get(route('research-profiles.personal'))->assertRedirect(route('research-profiles.show', $staff));
    $this->get(route('research-profiles.show', $staff))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty'));
    $this->get(route('reports.personal'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty')->where('scope', 'faculty'));

    $this->post(route('workspace.switch'), ['workspace' => 'staff'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('workspace', 'staff')->where('auth.user.role', $role));
    expect($staff->fresh()->role->value)->toBe($role);
})->with(['coordinator', 'admin']);

it('preserves the selected faculty workspace when visiting staff deep links', function (string $role, string $staffRoute) {
    $staff = User::factory()->{$role}()->create();
    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty']);

    $this->get(route($staffRoute))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty'));
    $this->get(route('news.index'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty'));

    $this->post(route('workspace.switch'), ['workspace' => 'staff'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'staff'));
})->with([
    ['coordinator', 'coordinator.evaluations.index'],
    ['admin', 'admin.approvals.index'],
]);

it('rejects invalid workspace values without changing the existing selection', function (mixed $value) {
    $staff = User::factory()->admin()->create();
    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty']);
    $this->post(route('workspace.switch'), ['workspace' => $value])->assertSessionHasErrors('workspace');
    $this->get(route('faculty.submissions.index'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty'));
})->with(['invalid' => 'admin', 'empty' => '', 'array' => [['faculty']]]);

it('rejects guests, inactive staff and faculty from switching', function () {
    $this->post(route('workspace.switch'), ['workspace' => 'faculty'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->admin()->create(['is_active' => false]))
        ->post(route('workspace.switch'), ['workspace' => 'faculty'])->assertForbidden();
    $this->actingAs(User::factory()->faculty()->create())
        ->post(route('workspace.switch'), ['workspace' => 'staff'])->assertForbidden();
    $this->get(route('admin.approvals.index'))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertForbidden();
});

it('ignores a workspace belonging to another user or an old role', function () {
    $staff = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty']);
    $this->actingAs($other)->get(route('faculty.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace', 'staff'));

    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty']);
    $staff->update(['role' => 'coordinator']);
    $this->actingAs($staff->fresh())->get(route('faculty.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace', 'staff')->where('auth.user.role', 'coordinator'));
    $staff->update(['role' => 'admin']);
    $this->actingAs($staff->fresh())->get(route('faculty.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace', 'staff'));

    $staff->update(['role' => 'coordinator']);
    $this->actingAs($staff->fresh());
    $this->withSession(['workspace' => ['user_id' => $staff->id, 'role' => 'admin', 'selection' => 'faculty']])
        ->get(route('admin.approvals.index'))->assertForbidden();
});

it('preserves personal data scope and blocks self review in faculty workspace', function (string $role) {
    $college = College::query()->create(['code' => 'WS', 'name' => 'Workspace College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'WS', 'name' => 'Workspace Department']);
    $staff = User::factory()->{$role}()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $other = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $year = AcademicYear::query()->create(['name' => 'Workspace year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $type = ResearchType::query()->create(['name' => 'Workspace research', 'slug' => 'workspace_research', 'is_active' => true, 'has_authors' => false, 'sort_order' => 999]);
    $attributes = ['department_id' => $department->id, 'college_id' => $college->id, 'academic_year_id' => $year->id, 'research_type' => $type->slug, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()];
    $own = ResearchSubmission::query()->create($attributes + ['faculty_id' => $staff->id, 'title' => 'Own study', 'authors' => [$staff->name]]);
    $otherSubmission = ResearchSubmission::query()->create($attributes + ['faculty_id' => $other->id, 'title' => 'Other study', 'authors' => [$other->name]]);

    $this->actingAs($staff)->post(route('workspace.switch'), ['workspace' => 'faculty', 'role' => 'admin', 'user_id' => $other->id]);
    $this->get(route('faculty.submissions.index'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty')->has('submissions.data', 1));
    $this->get(route('faculty.submissions.show', $otherSubmission))->assertForbidden();
    $this->get(route('reports.personal', ['period' => 'annual', 'year' => now()->year]))
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'faculty')->has('submissions', 1)->where('submissions.0.uuid', $own->uuid));
    $this->get(route($role === 'admin' ? 'admin.approvals.show' : 'coordinator.evaluations.show', $own))->assertForbidden();
    expect($staff->fresh()->role->value)->toBe($role);
})->with(['coordinator', 'admin']);
