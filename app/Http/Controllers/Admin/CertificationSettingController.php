<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificationSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Certification/Settings', [
            'settings' => CertificationSetting::query()->find(1) ?? new CertificationSetting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'office_name' => ['required', 'string', 'max:255'],
            'heading' => ['required', 'string', 'max:255'],
            'additional_text' => ['nullable', 'string', 'max:2000'],
            'first_signatory_name' => ['nullable', 'string', 'max:255'],
            'first_signatory_title' => ['nullable', 'string', 'max:255'],
            'second_signatory_name' => ['nullable', 'string', 'max:255'],
            'second_signatory_title' => ['nullable', 'string', 'max:255'],
            'allow_non_scopus' => ['sometimes', 'boolean'],
        ]);

        $settings = CertificationSetting::query()->find(1) ?? new CertificationSetting;
        $settings->id = 1;
        $settings->fill($data)->save();

        return back()->with('success', 'Certificate settings saved.');
    }
}
