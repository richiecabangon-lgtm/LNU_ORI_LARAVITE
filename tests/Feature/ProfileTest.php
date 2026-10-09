<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
