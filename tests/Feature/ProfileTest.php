<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function profileOrganizations(): array
{
    $college = College::query()->create(['code' => 'ONE', 'name' => 'First College', 'is_active' => true]);
    $otherCollege = College::query()->create(['code' => 'TWO', 'name' => 'Second College', 'is_active' => true]);
    $inactiveCollege = College::query()->create(['code' => 'OLD', 'name' => 'Old College', 'is_active' => false]);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'ONE', 'name' => 'First Department', 'is_active' => true]);
    $otherDepartment = Department::query()->create(['college_id' => $otherCollege->id, 'code' => 'TWO', 'name' => 'Second Department', 'is_active' => true]);
    $inactiveDepartment = Department::query()->create(['college_id' => $college->id, 'code' => 'OLD', 'name' => 'Old Department', 'is_active' => false]);
    $inactiveCollegeDepartment = Department::query()->create(['college_id' => $inactiveCollege->id, 'code' => 'OLD', 'name' => 'Old College Department', 'is_active' => true]);

    return compact('college', 'otherCollege', 'inactiveCollege', 'department', 'otherDepartment', 'inactiveDepartment', 'inactiveCollegeDepartment');
}

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile photo uploads are stored and shared with a same-origin URL', function () {
    Storage::fake('public', ['url' => 'http://localhost:8000/storage']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
        ])
        ->assertRedirect();

    $photoPath = $user->fresh()->profile_photo_path;
    Storage::disk('public')->assertExists($photoPath);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profilePhotoUrl', '/storage/'.$photoPath)
            ->where('auth.user.profile_photo_url', '/storage/'.$photoPath));
});

test('profile photo can be removed from public storage', function () {
    Storage::fake('public', ['url' => 'http://localhost:8000/storage']);
    $user = User::factory()->create();
    $photoPath = UploadedFile::fake()->image('profile.jpg')->store('profile-photos/'.$user->id, 'public');
    $user->update(['profile_photo_path' => $photoPath]);

    $this->actingAs($user)
        ->delete(route('profile.photo.delete'))
        ->assertRedirect();

    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($photoPath);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'academic_rank' => 'Associate Professor',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertSame('Associate Professor', $user->academic_rank);
    $this->assertNull($user->email_verified_at);
});

test('staff cannot change their assignment through their profile', function (string $role) {
    $user = User::factory()->{$role}()->create(['designation' => 'Original']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Changed Name',
        'email' => $user->email,
        'designation' => 'Changed',
        'college_id' => 999999,
        'department_id' => 999999,
        'role' => 'admin',
    ])->assertSessionHasErrors(['college_id', 'department_id']);

    $user->refresh();
    expect($user->name)->not->toBe('Changed Name')
        ->and($user->designation)->toBe('Original')
        ->and($user->role->value)->toBe($role)
        ->and($user->college_id)->toBeNull()
        ->and($user->department_id)->toBeNull();
})->with(['coordinator', 'admin']);

test('faculty can update and clear their assignment without changing protected attributes', function () {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id, 'employee_id' => 'FAC-1']);
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'designation' => 'Research Lead',
        'college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id,
        'role' => 'admin', 'is_active' => false, 'employee_id' => 'ATTACK',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'id' => $user->id, 'role' => 'faculty', 'is_active' => true, 'employee_id' => 'FAC-1', 'designation' => 'Research Lead',
        'college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id,
    ]);
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertForbidden();
    $this->post(route('workspace.switch'), ['workspace' => 'staff'])->assertForbidden();

    $this->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'college_id' => null, 'department_id' => null, 'designation' => null,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => null, 'department_id' => null, 'designation' => null]);
});

test('omitted organization fields preserve assignments and partial changes must remain consistent', function () {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id, 'designation' => 'Existing']);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id, 'designation' => 'Existing']);

    $this->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, 'college_id' => $organization['otherCollege']->id])
        ->assertSessionHasErrors('department_id');
    $this->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, 'college_id' => null])
        ->assertSessionHasErrors('department_id');
    $this->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, 'department_id' => $organization['otherDepartment']->id])
        ->assertSessionHasErrors('department_id');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);

    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'department_id' => null])->assertSessionHasNoErrors();
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'college_id' => $organization['otherCollege']->id])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => $organization['otherCollege']->id, 'department_id' => null]);
});

test('invalid organization identifiers are rejected without saving any fields', function (string $field, mixed $value) {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, $field => $value])
        ->assertSessionHasErrors($field);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
})->with([
    'missing college' => ['college_id', 999999], 'missing department' => ['department_id', 999999],
    'array college' => ['college_id', [1]], 'array department' => ['department_id', [1]],
    'text college' => ['college_id', 'invalid'], 'negative department' => ['department_id', -1],
]);

test('faculty cannot select inactive organizations but can retain existing inactive assignments', function () {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'college_id' => $organization['inactiveCollege']->id, 'department_id' => null,
    ])->assertSessionHasErrors('college_id');
    $this->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'department_id' => $organization['inactiveDepartment']->id,
    ])->assertSessionHasErrors('department_id');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);

    $user->update(['college_id' => $organization['inactiveCollege']->id, 'department_id' => $organization['inactiveCollegeDepartment']->id]);
    $this->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'college_id' => $organization['inactiveCollege']->id, 'department_id' => $organization['inactiveCollegeDepartment']->id,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => $organization['inactiveCollege']->id, 'department_id' => $organization['inactiveCollegeDepartment']->id]);
});

test('inactive departments can only be retained and inactive colleges cannot receive new departments', function () {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['inactiveDepartment']->id]);
    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
        ->has('colleges', 2)->has('departments', 3)->where('departments.1.id', $organization['inactiveDepartment']->id));
    $this->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'college_id' => $organization['college']->id, 'department_id' => $organization['inactiveDepartment']->id,
    ])->assertSessionHasNoErrors();

    $user->update(['college_id' => $organization['inactiveCollege']->id, 'department_id' => null]);
    $this->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'department_id' => $organization['inactiveCollegeDepartment']->id,
    ])->assertSessionHasErrors('department_id');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'college_id' => $organization['inactiveCollege']->id, 'department_id' => null]);
});

test('designation can be updated and cleared by every role', function (string $role) {
    $user = User::factory()->{$role}()->create(['designation' => 'Original']);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'designation' => str_repeat('A', 255)])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'designation' => str_repeat('A', 255)]);
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'designation' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'designation' => null]);
})->with(['faculty', 'coordinator', 'admin']);

test('designation rejects invalid values without changing the profile', function (mixed $value) {
    $user = User::factory()->faculty()->create(['designation' => 'Original']);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, 'designation' => $value])
        ->assertSessionHasErrors('designation');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'designation' => 'Original']);
})->with(['too long' => [str_repeat('A', 256)], 'array' => [['Research Lead']]]);

test('profile assignment data exposes only selectable catalog entries and the current inactive assignment', function () {
    $organization = profileOrganizations();
    $user = User::factory()->faculty()->create(['college_id' => $organization['inactiveCollege']->id, 'department_id' => $organization['inactiveCollegeDepartment']->id, 'designation' => 'Lead']);
    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('user.designation', 'Lead')->where('user.college_id', $organization['inactiveCollege']->id)
        ->where('user.department_id', $organization['inactiveCollegeDepartment']->id)
        ->where('assignment.college', 'Old College')->where('assignment.department', 'Old College Department')
        ->has('colleges', 3)->has('departments', 3)
        ->where('colleges.1.id', $organization['inactiveCollege']->id)
        ->where('departments.1.id', $organization['inactiveCollegeDepartment']->id));

    $staff = User::factory()->coordinator()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $this->actingAs($staff)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('assignment.college', 'First College')->where('assignment.department', 'First Department')
        ->has('colleges', 0)->has('departments', 0));
});

test('guests and inactive users cannot update profile information', function () {
    $this->patch(route('profile.update'), [])->assertRedirect(route('login'));
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $user = User::factory()->faculty()->create(['is_active' => false, 'designation' => 'Original']);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email, 'designation' => 'Changed', 'role' => 'admin'])
        ->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'designation' => 'Original', 'role' => 'faculty', 'is_active' => false]);
});

test('staff cannot change assignments or roles from their faculty workspace', function (string $role) {
    $organization = profileOrganizations();
    $user = User::factory()->{$role}()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id, 'designation' => 'Original']);
    $this->actingAs($user)->post(route('workspace.switch'), ['workspace' => 'faculty'])->assertRedirect();
    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('workspace', 'faculty')->where('auth.user.role', $role)->has('colleges', 0));

    $this->patch(route('profile.update'), [
        'name' => 'Changed', 'email' => $user->email, 'designation' => 'Changed',
        'college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id,
        'role' => 'admin', 'is_active' => false,
    ])->assertSessionHasErrors(['college_id', 'department_id']);
    $this->assertDatabaseHas('users', [
        'id' => $user->id, 'name' => $user->name, 'role' => $role, 'is_active' => true, 'designation' => 'Original',
        'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id,
    ]);
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'designation' => 'Updated', 'role' => 'admin'])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => $role, 'designation' => 'Updated', 'department_id' => $organization['department']->id]);
    if ($role === 'coordinator') {
        $this->get(route('admin.users.index'))->assertForbidden();
    }
})->with(['coordinator', 'admin']);

test('faculty assignment changes preserve historical submissions and coordinator visibility', function () {
    $organization = profileOrganizations();
    $faculty = User::factory()->faculty()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $otherFaculty = User::factory()->faculty()->create(['college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id]);
    $oldCoordinator = User::factory()->coordinator()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $newCoordinator = User::factory()->coordinator()->create(['college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id]);
    $year = AcademicYear::query()->create(['name' => 'Profile year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $historical = ResearchSubmission::query()->create([
        'faculty_id' => $faculty->id, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id,
        'academic_year_id' => $year->id, 'title' => 'Historical study', 'research_type' => 'journal_article',
        'authors' => [$faculty->name], 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
    ]);
    $foreign = ResearchSubmission::query()->create([
        'faculty_id' => $otherFaculty->id, 'college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id,
        'academic_year_id' => $year->id, 'title' => 'Foreign study', 'research_type' => 'journal_article',
        'authors' => [$otherFaculty->name], 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
    ]);

    $this->actingAs($faculty)->get(route('faculty.submissions.show', $foreign))->assertForbidden();
    $this->patch(route('profile.update'), [
        'name' => $faculty->name, 'email' => $faculty->email, 'college_id' => $organization['otherCollege']->id,
        'department_id' => $organization['otherDepartment']->id, 'role' => 'coordinator',
    ])->assertSessionHasNoErrors();
    $this->get(route('faculty.submissions.show', $foreign))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertForbidden();
    $this->assertDatabaseHas('research_submissions', [
        'id' => $historical->id, 'faculty_id' => $faculty->id, 'college_id' => $organization['college']->id, 'department_id' => $organization['department']->id,
    ]);
    expect($faculty->fresh()->can('view', $historical))->toBeTrue()
        ->and($oldCoordinator->can('view', $historical))->toBeTrue()
        ->and($newCoordinator->can('view', $historical))->toBeFalse();
    $this->actingAs($oldCoordinator)->get(route('coordinator.evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1)->where('submissions.data.0.uuid', $historical->uuid));
    $this->actingAs($newCoordinator)->get(route('coordinator.evaluations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1)->where('submissions.data.0.uuid', $foreign->uuid));
    $this->get(route('coordinator.evaluations.show', $historical))->assertForbidden();
});

test('a coordinator cannot move their review scope with a crafted profile request', function () {
    $organization = profileOrganizations();
    $coordinator = User::factory()->coordinator()->create(['college_id' => $organization['college']->id, 'department_id' => $organization['department']->id]);
    $faculty = User::factory()->faculty()->create();
    $year = AcademicYear::query()->create(['name' => 'Coordinator profile year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $foreign = ResearchSubmission::query()->create([
        'faculty_id' => $faculty->id, 'college_id' => $organization['otherCollege']->id, 'department_id' => $organization['otherDepartment']->id,
        'academic_year_id' => $year->id, 'title' => 'Foreign scope study', 'research_type' => 'journal_article',
        'authors' => [$faculty->name], 'status' => SubmissionStatus::Submitted, 'submitted_at' => now(),
    ]);
    $this->actingAs($coordinator)->post(route('workspace.switch'), ['workspace' => 'faculty']);
    $this->patch(route('profile.update'), [
        'name' => $coordinator->name, 'email' => $coordinator->email, 'college_id' => $organization['otherCollege']->id,
        'department_id' => $organization['otherDepartment']->id, 'role' => 'admin',
    ])->assertSessionHasErrors(['college_id', 'department_id']);
    $this->get(route('coordinator.evaluations.show', $foreign))->assertForbidden();
    $this->get(route('coordinator.evaluations.index'))->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $coordinator->id, 'role' => 'coordinator', 'department_id' => $organization['department']->id]);
});

test('scopus profile can be saved updated and removed', function () {
    $user = User::factory()->faculty()->create();

    foreach (['https://www.scopus.com/authid/detail.uri?authorId=123', 'http://scopus.com/pages/author/456', null] as $url) {
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'scopus_url' => $url,
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'scopus_url' => $url]);
        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('user.scopus_url', $url)
            ->where('auth.user.scopus_url', $url));
    }
});

test('invalid scopus profile links are rejected without changing the saved link', function (mixed $url) {
    $savedUrl = 'https://www.scopus.com/authid/detail.uri?authorId=123';
    $user = User::factory()->create(['scopus_url' => $savedUrl]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'scopus_url' => $url,
    ])->assertSessionHasErrors('scopus_url');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'scopus_url' => $savedUrl]);
})->with([
    'javascript' => 'javascript:alert(1)',
    'other scheme' => 'ftp://www.scopus.com/authid/detail.uri?authorId=123',
    'lookalike host' => 'https://www.scopus.com.example.com/authid/detail.uri?authorId=123',
    'misleading user info' => 'https://www.scopus.com@evil.example/profile',
    'credentials' => 'https://user@www.scopus.com/authid/detail.uri?authorId=123',
    'not a string' => [['https://www.scopus.com']],
]);

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
