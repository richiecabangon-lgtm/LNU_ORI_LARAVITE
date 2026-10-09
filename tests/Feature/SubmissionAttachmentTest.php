<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function submissionWithAttachments(array $files = ['a.pdf', 'b.png', 'c.docx']): array
{
    Storage::fake('public');
    $college = College::query()->create(['code' => 'AT', 'name' => 'Attach College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'AD', 'name' => 'Attach Dept']);
    $year = AcademicYear::query()->create(['name' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'is_current' => true, 'is_active' => true]);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $submission = ResearchSubmission::query()->create([
        'faculty_id' => $faculty->id, 'college_id' => $college->id, 'department_id' => $department->id,
        'academic_year_id' => $year->id, 'title' => 'Attachment study', 'research_type' => 'journal_article',
        'authors' => [$faculty->name], 'status' => SubmissionStatus::Draft, 'claimed_points' => 10,
    ]);
    $attachments = collect($files)->map(function (string $name) use ($submission, $faculty) {
        Storage::disk('public')->put('research-attachments/'.$name, 'content');

        return $submission->attachments()->create([
            'disk' => 'public', 'path' => 'research-attachments/'.$name, 'original_name' => $name,
            'size' => 7, 'category' => 'evidence', 'uploaded_by' => $faculty->id,
        ]);
    });

    return [$faculty, $submission, $attachments, $department];
}

it('previews pdf and images inline for the owner and refuses unsupported types', function () {
    [$faculty, $submission, $attachments] = submissionWithAttachments();

    $this->actingAs($faculty)->get(route('submission-attachments.view', $attachments[0]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($faculty)->get(route('submission-attachments.view', $attachments[1]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($faculty)->get(route('submission-attachments.view', $attachments[2]))->assertNotFound();
    $this->actingAs($faculty)->get(route('submission-attachments.download', $attachments[2]))->assertOk();
});

it('lets the department coordinator and admin preview but not unrelated users', function () {
    [$faculty, $submission, $attachments, $department] = submissionWithAttachments();
    $coordinator = User::factory()->coordinator()->create(['department_id' => $department->id]);
    $outsider = User::factory()->faculty()->create();
    $otherCoordinator = User::factory()->coordinator()->create();
    $url = route('submission-attachments.view', $attachments[0]);

    $this->actingAs($coordinator)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $this->actingAs($otherCoordinator)->get($url)->assertForbidden();
});

it('exposes attachment preview details on the faculty submission page', function () {
    [$faculty, $submission] = submissionWithAttachments();

    $this->actingAs($faculty)->get(route('faculty.submissions.show', $submission))
        ->assertInertia(fn ($page) => $page->has('submission.attachments', 3)
            ->where('submission.attachments.0.kind', 'pdf')
            ->where('submission.attachments.2.kind', 'file'));
});

it('lets the owner remove an attachment only while the submission is editable', function () {
    [$faculty, $submission, $attachments] = submissionWithAttachments();

    $this->actingAs($faculty)->delete(route('submission-attachments.destroy', $attachments[0]))->assertRedirect();
    Storage::disk('public')->assertMissing('research-attachments/a.pdf');

    $submission->update(['status' => SubmissionStatus::Approved]);
    $this->actingAs($faculty)->delete(route('submission-attachments.destroy', $attachments[1]))->assertForbidden();
});

it('previews authorization proofs inline for permitted users only', function () {
    [$faculty, $submission, , $department] = submissionWithAttachments([]);
    Storage::fake('local');
    Storage::disk('local')->put('proofs/consent.pdf', '%PDF-1.4');
    $author = $submission->authorshipAllocations()->create([
        'name' => 'Co Author', 'author_order' => 1, 'allocated_points' => 5,
        'authorization_disk' => 'local', 'authorization_path' => 'proofs/consent.pdf', 'authorization_original_name' => 'consent.pdf',
    ]);

    $this->actingAs($faculty)->get(route('research-authorizations.view', $author))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(User::factory()->faculty()->create())->get(route('research-authorizations.view', $author))->assertForbidden();
    $this->actingAs($faculty)->get(route('faculty.submissions.show', $submission))
        ->assertInertia(fn ($page) => $page->where('submission.authorship_allocations.0.authorization_kind', 'pdf'));
});
