<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\ResearchSubmission;
use App\Models\ResearchSubmissionAuthor;
use App\Models\ResearchType;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function duplicateFixture(): array
{
    $year = AcademicYear::query()->create([
        'name' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'is_current' => true, 'is_active' => true,
    ]);
    $type = ResearchType::query()->create([
        'name' => 'Duplicate test research', 'slug' => 'duplicate_test_research',
        'unit' => 'output', 'has_authors' => true, 'is_active' => true,
    ]);
    $type->pointSetting()->create(['points_per_output' => 90]);

    return [
        'year' => $year,
        'type' => $type,
        'lead' => User::factory()->faculty()->create(),
        'second' => User::factory()->faculty()->create(),
        'third' => User::factory()->faculty()->create(),
    ];
}

function duplicatePayload(array $fixture, string $title = 'Shared Research Title'): array
{
    $authors = [$fixture['lead'], $fixture['second'], $fixture['third']];

    return [
        'title' => $title,
        'research_type' => $fixture['type']->slug,
        'academic_year_id' => $fixture['year']->id,
        'authors' => array_map(fn (User $author) => $author->name, $authors),
        'author_details' => array_map(fn (User $author) => [
            'name' => $author->name, 'email' => $author->email, 'allocated_points' => 30,
        ], $authors),
        'submit_now' => true,
    ];
}

function insertDuplicateAfterValidation(array $fixture, string $title): void
{
    $inserted = false;
    DB::listen(function (QueryExecuted $query) use ($fixture, $title, &$inserted): void {
        if ($inserted || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'normalized_title_hash')) {
            return;
        }

        $inserted = true;
        $submission = ResearchSubmission::query()->create([
            'faculty_id' => $fixture['lead']->id,
            'academic_year_id' => $fixture['year']->id,
            'title' => $title,
            'research_type' => $fixture['type']->slug,
            'authors' => [$fixture['lead']->name, $fixture['second']->name],
            'status' => SubmissionStatus::Submitted,
            'claimed_points' => 90,
        ]);
        $submission->authorshipAllocations()->create([
            'faculty_id' => $fixture['second']->id, 'name' => $fixture['second']->name,
            'author_order' => 1, 'allocated_points' => 30,
        ]);
    });
}

it('blocks linked coauthors while preserving a generic message for unrelated faculty and the owner', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();

    $payload['title'] = "  SHARED  research\tTITLE  ";
    foreach ([$fixture['second'], $fixture['third']] as $coauthor) {
        $this->actingAs($coauthor)->post(route('faculty.submissions.store'), $payload)
            ->assertSessionHasErrors(['title' => 'This title and involvement type has already been submitted by a co-author.']);
    }
    $unrelated = User::factory()->faculty()->create();
    foreach ([$unrelated, $fixture['lead']] as $faculty) {
        $this->actingAs($faculty)->post(route('faculty.submissions.store'), $payload)
            ->assertSessionHasErrors(['title' => 'An involvement with this title and type already exists.']);
    }

    expect(ResearchSubmission::query()->count())->toBe(1);
});

it('keeps linked coauthor drafts private and allows the same title under another type', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    $payload['submit_now'] = false;
    $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();

    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload)
        ->assertSessionHasErrors(['title' => 'An involvement with this title and type already exists.']);

    $otherType = ResearchType::query()->create([
        'name' => 'Another test research', 'slug' => 'another_test_research',
        'unit' => 'output', 'has_authors' => true, 'is_active' => true,
    ]);
    $otherType->pointSetting()->create(['points_per_output' => 90]);
    $payload['research_type'] = $otherType->slug;
    $this->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    expect(ResearchSubmission::query()->count())->toBe(2);
});

it('does not infer authorship from an unlinked name or email', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    ResearchSubmission::query()->firstOrFail()->authorshipAllocations()
        ->where('faculty_id', $fixture['second']->id)->update(['faculty_id' => null]);

    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload)
        ->assertSessionHasErrors(['title' => 'An involvement with this title and type already exists.']);
});

it('allows a new involvement after the matching involvement is soft deleted', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    $payload['submit_now'] = false;
    $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    ResearchSubmission::query()->firstOrFail()->delete();

    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    expect(ResearchSubmission::query()->count())->toBe(1);
});

it('allows a self-update and rejects an update matching another submitted involvement', function () {
    $fixture = duplicateFixture();
    $draftPayload = duplicatePayload($fixture, 'Original Draft');
    $draftPayload['submit_now'] = false;
    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $draftPayload)->assertSessionHasNoErrors();
    $draft = ResearchSubmission::query()->where('title', 'Original Draft')->firstOrFail();
    $this->put(route('faculty.submissions.update', $draft), $draftPayload)->assertSessionHasNoErrors();

    $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), duplicatePayload($fixture))->assertSessionHasNoErrors();
    $draftPayload['title'] = ' SHARED research title ';
    $this->actingAs($fixture['second'])->put(route('faculty.submissions.update', $draft), $draftPayload)
        ->assertSessionHasErrors(['title' => 'This title and involvement type has already been submitted by a co-author.']);
    expect($draft->fresh()->title)->toBe('Original Draft');
});

it('translates a verified title and type insert race after rollback', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    insertDuplicateAfterValidation($fixture, $payload['title']);

    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload)
        ->assertSessionHasErrors(['title' => 'This title and involvement type has already been submitted by a co-author.']);
    expect(ResearchSubmission::query()->count())->toBe(1);
});

it('translates a verified title and type update race after rollback', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture, 'Original Draft');
    $payload['submit_now'] = false;
    $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
    $draft = ResearchSubmission::query()->firstOrFail();
    $payload['title'] = 'Shared Research Title';
    insertDuplicateAfterValidation($fixture, $payload['title']);

    $this->put(route('faculty.submissions.update', $draft), $payload)
        ->assertSessionHasErrors(['title' => 'This title and involvement type has already been submitted by a co-author.']);
    expect($draft->fresh()->title)->toBe('Original Draft');
});

it('rethrows a title constraint failure when no duplicate remains after rollback', function () {
    $fixture = duplicateFixture();
    ResearchSubmission::creating(function (): void {
        throw new UniqueConstraintViolationException('sqlite', 'insert into research_submissions', [], new RuntimeException(
            'UNIQUE constraint failed: research_submissions.normalized_title_hash, research_submissions.research_type',
        ));
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), duplicatePayload($fixture)))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('does not translate an unrelated unique failure even when a duplicate exists', function () {
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    insertDuplicateAfterValidation($fixture, $payload['title']);
    ResearchSubmission::creating(function (ResearchSubmission $submission) use ($fixture): void {
        if ($submission->faculty_id === $fixture['second']->id) {
            throw new UniqueConstraintViolationException('sqlite', 'insert into research_submissions', [], new RuntimeException(
                'UNIQUE constraint failed: research_submissions.reference_code',
            ));
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($fixture['second'])->post(route('faculty.submissions.store'), $payload))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(ResearchSubmission::query()->count())->toBe(1);
});

it('cleans new author files after unrelated unique failures without deleting existing files', function (bool $isUpdate) {
    Storage::fake('local');
    Storage::disk('local')->put('existing-proof.pdf', 'existing');
    $fixture = duplicateFixture();
    $payload = duplicatePayload($fixture);
    $payload['submit_now'] = false;

    if ($isUpdate) {
        $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload)->assertSessionHasNoErrors();
        $draft = ResearchSubmission::query()->firstOrFail();
    }

    $payload['author_details'][1]['authorization_file'] = UploadedFile::fake()->create('new-proof.pdf', 20, 'application/pdf');
    ResearchSubmissionAuthor::creating(function (ResearchSubmissionAuthor $author): void {
        if ($author->author_order === 2) {
            throw new UniqueConstraintViolationException('sqlite', 'insert into research_submission_authors', [], new RuntimeException(
                'UNIQUE constraint failed: research_submission_authors.research_submission_id, research_submission_authors.author_order',
            ));
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $isUpdate
        ? $this->actingAs($fixture['lead'])->put(route('faculty.submissions.update', $draft), $payload)
        : $this->actingAs($fixture['lead'])->post(route('faculty.submissions.store'), $payload))
        ->toThrow(UniqueConstraintViolationException::class);

    Storage::disk('local')->assertExists('existing-proof.pdf');
    expect(Storage::disk('local')->allFiles('research-authorizations'))->toBe([])
        ->and(ResearchSubmission::query()->count())->toBe($isUpdate ? 1 : 0);
})->with(['create' => false, 'update' => true]);
