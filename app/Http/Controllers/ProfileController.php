<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\College;
use App\Models\Department;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $user->load(['college:id,name', 'department:id,name']);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'user' => $user->only([
                'id', 'name', 'email', 'email_verified_at', 'academic_rank', 'orcid_url',
                'google_scholar_url', 'scopus_url', 'institutional_profile_url', 'profile_photo_path', 'theme_preference', 'research_interests',
                'designation', 'college_id', 'department_id',
            ]),
            'assignment' => [
                'college' => $user->college?->name,
                'department' => $user->department?->name,
            ],
            'colleges' => $user->isFaculty() ? College::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->college_id))
                ->orderBy('name')->get(['id', 'name', 'is_active']) : [],
            'departments' => $user->isFaculty() ? Department::query()
                ->where(fn ($query) => $query
                    ->where(fn ($active) => $active->where('is_active', true)->whereHas('college', fn ($college) => $college->where('is_active', true)))
                    ->orWhere('id', $user->department_id))
                ->orderBy('name')->get(['id', 'name', 'college_id', 'is_active']) : [],
            'profilePhotoUrl' => $user->profile_photo_url,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $previousPath = $user->profile_photo_path;
        $user->profile_photo_path = $data['profile_photo']->store('profile-photos/'.$user->id, 'public');
        $user->save();

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return back()->with('success', 'Profile photo updated.');
    }

    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
            $user->save();
        }

        return back()->with('success', 'Profile photo removed.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_preference' => ['required', 'in:light,dark'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Display preference saved.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
