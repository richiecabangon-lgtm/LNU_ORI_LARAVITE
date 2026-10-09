<?php

namespace App\Http\Middleware;

use App\Models\FpesReportSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'designation' => $user->designation,
                    'academic_rank' => $user->academic_rank,
                    'orcid_url' => $user->orcid_url,
                    'google_scholar_url' => $user->google_scholar_url,
                    'institutional_profile_url' => $user->institutional_profile_url,
                    'profile_photo_url' => $user->profile_photo_url,
                    'theme_preference' => $user->theme_preference ?? 'light',
                    'college_id' => $user->college_id,
                    'department_id' => $user->department_id,
                ] : null,
            ],
            'appearance' => [
                'theme' => $user?->theme_preference ?? 'light',
            ],
            'notifications' => $user ? [
                'unreadCount' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->latest()->limit(8)->get()->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
            ] : ['unreadCount' => 0, 'items' => []],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'institution' => 'Leyte Normal University',
                'office' => 'Office of Research and Innovation',
                'logo_url' => fn (): ?string => ($logoPath = FpesReportSetting::query()->value('logo_path'))
                    ? '/storage/'.ltrim($logoPath, '/')
                    : null,
            ],
        ];
    }
}
