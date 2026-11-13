<?php

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows an admin to create a user account with an organizational assignment', function () {
    $admin = User::factory()->admin()->create();
    $college = College::query()->create(['code' => 'UC1', 'name' => 'User Test College']);
    $department = Department::query()->create([
        'college_id' => $college->id,
        'code' => 'UD1',
        'name' => 'User Test Department',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Faculty Member',
            'email' => 'new.faculty@example.edu',
            'password' => 'FacultyPass123!',
            'role' => 'faculty',
            'employee_id' => 'EMP-1001',
            'college_id' => $college->id,
            'department_id' => $department->id,
            'designation' => 'Lecturer',
            'academic_rank' => 'Assistant Professor',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $createdUser = User::query()->where('email', 'new.faculty@example.edu')->firstOrFail();

    expect($createdUser->name)->toBe('New Faculty Member')
        ->and($createdUser->college_id)->toBe($college->id)
        ->and($createdUser->department_id)->toBe($department->id)
        ->and($createdUser->academic_rank)->toBe('Assistant Professor')
        ->and($createdUser->is_active)->toBeTrue()
        ->and(Hash::check('FacultyPass123!', $createdUser->password))->toBeTrue();
});

it('rejects a department that does not belong to the selected college', function () {
    $admin = User::factory()->admin()->create();
    $college = College::query()->create(['code' => 'UC2', 'name' => 'Selected College']);
    $otherCollege = College::query()->create(['code' => 'UC3', 'name' => 'Other College']);
    $otherDepartment = Department::query()->create([
        'college_id' => $otherCollege->id,
        'code' => 'UD2',
        'name' => 'Other College Department',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'Mismatched Department User',
            'email' => 'mismatch@example.edu',
            'password' => 'FacultyPass123!',
            'role' => 'faculty',
            'college_id' => $college->id,
            'department_id' => $otherDepartment->id,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('department_id');

    expect(User::query()->where('email', 'mismatch@example.edu')->exists())->toBeFalse();
});

it('allows admins to reassign an existing faculty member to another college and department', function () {
    $admin = User::factory()->admin()->create();
    $college = College::query()->create(['code' => 'UC4', 'name' => 'Original College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'UD4', 'name' => 'Original Department']);
    $newCollege = College::query()->create(['code' => 'UC5', 'name' => 'New College']);
    $newDepartment = Department::query()->create(['college_id' => $newCollege->id, 'code' => 'UD5', 'name' => 'New Department']);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);

    $this->actingAs($admin)->put(route('admin.users.update', $faculty), [
        'name' => $faculty->name,
        'email' => $faculty->email,
        'role' => 'faculty',
        'employee_id' => $faculty->employee_id,
        'college_id' => $newCollege->id,
        'department_id' => $newDepartment->id,
        'designation' => $faculty->designation,
        'academic_rank' => $faculty->academic_rank,
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $faculty->id,
        'college_id' => $newCollege->id,
        'department_id' => $newDepartment->id,
    ]);
});

it('rejects admin reassignment to a department from another college', function () {
    $admin = User::factory()->admin()->create();
    $college = College::query()->create(['code' => 'UC6', 'name' => 'Selected College']);
    $otherCollege = College::query()->create(['code' => 'UC7', 'name' => 'Other College']);
    $otherDepartment = Department::query()->create(['college_id' => $otherCollege->id, 'code' => 'UD7', 'name' => 'Other Department']);
    $faculty = User::factory()->faculty()->create();

    $this->actingAs($admin)->from(route('admin.users.index'))->put(route('admin.users.update', $faculty), [
        'name' => $faculty->name,
        'email' => $faculty->email,
        'role' => 'faculty',
        'employee_id' => $faculty->employee_id,
        'college_id' => $college->id,
        'department_id' => $otherDepartment->id,
        'designation' => $faculty->designation,
        'academic_rank' => $faculty->academic_rank,
        'is_active' => true,
    ])->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('department_id');

    $this->assertDatabaseHas('users', [
        'id' => $faculty->id,
        'college_id' => null,
        'department_id' => null,
    ]);
});
