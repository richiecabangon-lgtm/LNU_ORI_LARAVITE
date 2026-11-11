<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function evaluationScreeningFixture(): array
{
    Notification::fake();
    $college = College::query()->create(['code' => 'EVA', 'name' => 'Evaluation College']);
    $department = Department::query()->create(['college_id' => $college->id, 'code' => 'EVA', 'name' => 'Evaluation Department']);
    $faculty = User::factory()->faculty()->create(['college_id' => $college->id, 'department_id' => $department->id]);
    $coordinator = User::factory()->coordinator()->create(['department_id' => $department->id]);
    $year = AcademicYear::query()->create(['name' => 'Evaluation year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $submission = ResearchSubmission::query()->create([
        'faculty_id' => $faculty->id, 'college_id' => $college->id, 'department_id' => $department->id,
        'academic_year_id' => $year->id, 'title' => 'Evaluation research', 'research_type' => 'journal_article',
        'authors' => [$faculty->name], 'status' => SubmissionStatus::Submitted,
    ]);

    return compact('faculty', 'coordinator', 'submission');
}

function confirmedEvaluationPayload(): array
{
    return [
        'score' => 80, 'quality_rating' => 4, 'relevance_rating' => 4, 'documentation_rating' => 4,
        'review_confirmed' => true, 'recommendation' => 'recommend_approval', 'remarks' => 'Reviewed',
    ];
}

it('requires explicit review confirmation before submitting an evaluation', function (mixed $confirmation) {
    $fixture = evaluationScreeningFixture();
    $payload = confirmedEvaluationPayload();
    if ($confirmation === null) {
        unset($payload['review_confirmed']);
    } else {
        $payload['review_confirmed'] = $confirmation;
    }

    $this->actingAs($fixture['coordinator'])->post(route('coordinator.evaluations.store', $fixture['submission']), $payload)
        ->assertSessionHasErrors('review_confirmed');

    expect($fixture['submission']->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($fixture['submission']->evaluation()->exists())->toBeFalse();
})->with(['missing' => [null], 'false' => [false], 'zero' => [0]]);

it('requires every score and rating and rejects invalid values', function (string $field, mixed $value) {
    $fixture = evaluationScreeningFixture();
    $payload = confirmedEvaluationPayload();
    $payload[$field] = $value;

    $this->actingAs($fixture['coordinator'])->post(route('coordinator.evaluations.store', $fixture['submission']), $payload)
        ->assertSessionHasErrors($field);

    expect($fixture['submission']->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($fixture['submission']->evaluation()->exists())->toBeFalse();
})->with([
    'missing score' => ['score', null], 'negative score' => ['score', -1], 'score over 100' => ['score', 100.01],
    'missing quality' => ['quality_rating', null], 'low quality' => ['quality_rating', 0], 'high quality' => ['quality_rating', 6], 'fractional quality' => ['quality_rating', 2.5],
    'missing relevance' => ['relevance_rating', null], 'low relevance' => ['relevance_rating', 0], 'high relevance' => ['relevance_rating', 6], 'fractional relevance' => ['relevance_rating', 2.5],
    'missing documentation' => ['documentation_rating', null], 'low documentation' => ['documentation_rating', 0], 'high documentation' => ['documentation_rating', 6], 'fractional documentation' => ['documentation_rating', 2.5],
]);

it('accepts valid score and rating boundaries and a calculated score', function (float $score, int $rating, string $storedScore) {
    $fixture = evaluationScreeningFixture();
    $payload = confirmedEvaluationPayload();
    $payload['score'] = $score;
    $payload['quality_rating'] = $rating;
    $payload['relevance_rating'] = $rating;
    $payload['documentation_rating'] = $rating;

    $this->actingAs($fixture['coordinator'])->post(route('coordinator.evaluations.store', $fixture['submission']), $payload)
        ->assertSessionHasNoErrors();

    expect($fixture['submission']->fresh()->status)->toBe(SubmissionStatus::Evaluated)
        ->and($fixture['submission']->evaluation()->firstOrFail()->score)->toBe($storedScore);
})->with([
    'minimum override' => [0.0, 1, '0.00'],
    'equal weight suggestion' => [80.0, 4, '80.00'],
    'maximum' => [100.0, 5, '100.00'],
]);

it('stores a confirmed override and requires a fresh confirmation on re-evaluation', function () {
    $fixture = evaluationScreeningFixture();
    $submission = $fixture['submission'];
    $payload = confirmedEvaluationPayload();
    $payload['score'] = 75.5;
    $payload['recommendation'] = 'request_revision';

    $this->actingAs($fixture['coordinator'])->post(route('coordinator.evaluations.store', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::RevisionRequested)
        ->and($submission->evaluation()->firstOrFail()->score)->toBe('75.50');

    $submission->refresh()->update(['status' => SubmissionStatus::Submitted]);
    $this->get(route('coordinator.evaluations.show', $submission))->assertInertia(fn (Assert $page) => $page
        ->where('submission.evaluation.score', '75.50')
        ->where('submission.evaluation.quality_rating', 4)
        ->where('canEvaluate', true));
    $payload['review_confirmed'] = false;
    $this->post(route('coordinator.evaluations.store', $submission), $payload)->assertSessionHasErrors('review_confirmed');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->evaluation()->firstOrFail()->score)->toBe('75.50');

    $payload['review_confirmed'] = true;
    $payload['score'] = 100;
    $payload['recommendation'] = 'recommend_rejection';
    $this->post(route('coordinator.evaluations.store', $submission), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Evaluated)
        ->and($submission->evaluation()->count())->toBe(1)
        ->and($submission->evaluation()->firstOrFail()->score)->toBe('100.00');
});

it('rejects a confirmed evaluation from an unauthorized participant', function () {
    $fixture = evaluationScreeningFixture();
    $fixture['submission']->update(['faculty_id' => $fixture['coordinator']->id]);

    $this->actingAs($fixture['coordinator'])->post(route('coordinator.evaluations.store', $fixture['submission']), confirmedEvaluationPayload())
        ->assertForbidden();

    expect($fixture['submission']->fresh()->status)->toBe(SubmissionStatus::Submitted)
        ->and($fixture['submission']->evaluation()->exists())->toBeFalse();
});
