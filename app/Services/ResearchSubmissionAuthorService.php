<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\ResearchSubmissionAuthor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResearchSubmissionAuthorService
{
    /**
     * @param  list<array{name: string, email?: string|null, institution?: string|null, allocated_points: int|float|string, authorization_file?: UploadedFile|null}>  $authors
     */
    public function sync(ResearchSubmission $submission, array $authors, float $totalPoints): void
    {
        $authors = array_values(array_filter($authors, fn (array $author): bool => trim($author['name']) !== ''));
        if ($authors === []) {
            throw ValidationException::withMessages(['authors' => 'Add at least one paper author.']);
        }

        $authorIdentities = array_map(fn (array $author): string => mb_strtolower(trim((string) (($author['email'] ?? '') ?: $author['name']))), $authors);
        if (count(array_unique($authorIdentities)) !== count($authorIdentities)) {
            throw ValidationException::withMessages(['author_details' => 'Each paper author can only be listed once.']);
        }

        $existingAuthors = $submission->authorshipAllocations()->get();
        $existingProofs = $existingAuthors->keyBy(fn (ResearchSubmissionAuthor $author): string => mb_strtolower(trim((string) ($author->email ?: $author->name)))
        );

        $allocationsTotal = round(array_sum(array_map(fn (array $author): float => (float) $author['allocated_points'], $authors)), 2);
        if (abs($allocationsTotal - $totalPoints) > 0.01) {
            throw ValidationException::withMessages(['author_details' => 'Author allocations must add up to the point total for this research type.']);
        }

        $equalShare = $totalPoints / count($authors);
        foreach ($authors as $author) {
            $proofKey = mb_strtolower(trim((string) ($author['email'] ?? '') ?: $author['name']));
            $existingProof = $existingProofs->get($proofKey);
            $hasExistingProof = (bool) $existingProof?->authorization_path
                && abs((float) $existingProof->allocated_points - (float) $author['allocated_points']) <= 0.01;
            if ((float) $author['allocated_points'] < $equalShare - 0.02
                && empty($author['authorization_file'])
                && ! $hasExistingProof) {
                throw ValidationException::withMessages([
                    'author_details' => 'Upload authorization proof for each co-author receiving less than the equal-share allocation.',
                ]);
            }
        }

        $submittedNames = collect($authors)->map(fn (array $author): string => mb_strtolower(trim((string) ($author['email'] ?? '') ?: $author['name']))
        );

        foreach ($existingAuthors as $existingAuthor) {
            $key = mb_strtolower(trim((string) ($existingAuthor->email ?: $existingAuthor->name)));
            if (! $submittedNames->contains($key) && $existingAuthor->authorization_path) {
                Storage::disk($existingAuthor->authorization_disk ?? 'local')->delete($existingAuthor->authorization_path);
            }
        }

        $submission->authorshipAllocations()->delete();
        $submission->update([
            'authors' => array_map(fn (array $author): string => trim($author['name']), $authors),
        ]);
        $submitter = $submission->faculty()->first();

        foreach ($authors as $index => $author) {
            $email = trim((string) ($author['email'] ?? ''));
            $faculty = $email === ''
                ? null
                : User::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                    ->where('role', UserRole::Faculty)
                    ->where('is_active', true)
                    ->first();

            if (! $faculty) {
                $matchingFaculty = User::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($author['name']))])
                    ->where('role', UserRole::Faculty)
                    ->where('is_active', true)
                    ->limit(2)
                    ->get(['id', 'name', 'email', 'college_id', 'department_id']);

                if ($matchingFaculty->count() === 1) {
                    $faculty = $matchingFaculty->first();
                    $email = $email !== '' ? $email : $faculty->email;
                }
            }

            if (! $faculty && $submitter && mb_strtolower(trim($author['name'])) === mb_strtolower(trim($submitter->name))) {
                $faculty = $submitter;
            }
            $proof = $author['authorization_file'] ?? null;
            $previousProof = $existingProofs->get(mb_strtolower(trim($email !== '' ? $email : $author['name'])));
            $proofPath = $proof?->store('research-authorizations/'.$submission->uuid, 'local');

            if (! $proofPath && $previousProof?->authorization_path) {
                $proofPath = $previousProof->authorization_path;
            }

            if ($proofPath && $previousProof?->authorization_path && $proofPath !== $previousProof->authorization_path) {
                Storage::disk($previousProof->authorization_disk ?? 'local')->delete($previousProof->authorization_path);
            }

            ResearchSubmissionAuthor::query()->create([
                'research_submission_id' => $submission->id,
                'faculty_id' => $faculty?->id,
                'name' => trim($author['name']),
                'email' => $email !== '' ? $email : null,
                'institution' => trim((string) ($author['institution'] ?? '')) ?: null,
                'author_order' => $index + 1,
                'allocated_points' => $author['allocated_points'],
                'authorization_disk' => $proofPath ? 'local' : null,
                'authorization_path' => $proofPath,
                'authorization_original_name' => $proof?->getClientOriginalName() ?? $previousProof?->authorization_original_name,
            ]);
        }
    }
}
