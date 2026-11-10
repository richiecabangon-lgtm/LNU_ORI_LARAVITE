<?php

namespace App\Http\Controllers;

use App\Models\CertificationSetting;
use App\Models\ResearchSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ResearchCertificateController extends Controller
{
    public function show(Request $request, ResearchSubmission $submission): Response
    {
        Gate::authorize('viewCertificate', $submission);

        $submission->load('researchType');
        $nonce = Str::random(32);
        $content = view('certificates.research-involvement', [
            'submission' => $submission,
            'faculty' => $request->user(),
            'settings' => CertificationSetting::query()->find(1) ?? new CertificationSetting,
            'nonce' => $nonce,
            'letterTemplate' => base64_encode(file_get_contents(public_path('images/letter-template/image1.png'))),
        ])->render();

        $disposition = $request->routeIs('faculty.certificates.download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => $disposition.'; filename="research-involvement-'.$submission->uuid.'.html"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }
}
