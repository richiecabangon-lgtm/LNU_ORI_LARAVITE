<?php

namespace App\Http\Controllers;

use App\Models\SubmissionAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionAttachmentController extends Controller
{
    public function view(SubmissionAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->submission);
        abort_if($attachment->kind === 'file', 404);

        $contentType = match ($attachment->extension()) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        // Content type comes from the extension, never from the uploader's claimed MIME type.
        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function download(SubmissionAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->submission);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, SubmissionAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $attachment->submission);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
