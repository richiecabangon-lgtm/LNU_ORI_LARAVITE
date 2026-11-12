<?php

use App\Models\AcademicYear;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function brochureEvidenceMigration(): object
{
    return require database_path('migrations/2026_11_12_052040_add_brochure_default_evidence_requirements.php');
}

it('provides optional active evidence for every original non-IP type only', function () {
    $types = ResearchType::query()->whereNotNull('category')->with('evidenceRequirements')->get();

    expect($types)->toHaveCount(97);
    expect($types->where('category', 'Intellectual property')->every(fn ($type) => $type->evidenceRequirements->isEmpty()))->toBeTrue();
    expect($types->where('category', '!=', 'Intellectual property')->every(fn ($type) => $type->evidenceRequirements->isNotEmpty()))->toBeTrue();
    expect($types->flatMap->evidenceRequirements->every(fn ($requirement) => ! $requirement->is_required && $requirement->is_active))->toBeTrue();
    expect($types->flatMap->evidenceRequirements->pluck('name')->filter(fn ($name) => str_contains(strtolower($name), 'ethics') || str_contains(strtolower($name), 'rec exemption')))->toBeEmpty();
});

it('maps brochure evidence to each role and level', function (string $category, string $prefix, int $count, array $expected) {
    $types = ResearchType::query()->where('category', $category)->where('name', 'like', $prefix.'%')->with('evidenceRequirements')->get();

    expect($types)->toHaveCount($count);
    foreach ($types as $type) {
        expect($type->evidenceRequirements->pluck('name')->all())->toBe($expected);
    }
})->with([
    ['Completed and ongoing research', 'Completed unpublished', 2, ['Copy of completed research paper']],
    ['Completed and ongoing research', 'On-going', 2, ['Work plan', 'Accomplishment report']],
    ['Published research', 'Published individual', 5, ['Copy of published article', 'Copy of journal cover']],
    ['Research citations', 'Citation', 2, ['Copy of citing article']],
    ['Research-related activities', 'Attendance', 5, ['Certificate of attendance, participation, appreciation, or recognition', 'Special order for attendance or participation']],
    ['Research-related activities', 'Organizer / Coordinator / Facilitator', 5, ['Special order', 'Certificate as organizer or coordinator', 'Certificate as facilitator']],
    ['Research-related activities', 'Editor / Moderator / Judge', 5, ['Editorial role: special order', 'Editorial role: journal page listing editorial board or staff', 'Moderator: special order', 'Moderator: certificate', 'Judge: special order or invitation', 'Judge: certificate']],
    ['Research-related activities', 'Committee member', 5, ['Special order', 'Certificate as committee member']],
    ['Research-related activities', 'Resource person / Lecturer / Presenter / Speaker', 5, ['Invitation', 'Certificate as resource person, lecturer, speaker, or presenter', 'Special order for presenting']],
    ['Research-related activities', 'Consultant', 5, ['Certification as consultant']],
    ['Refereeing in a peer-reviewed journal', 'Referee', 8, ['Communication with journal editor', 'Copy of refereed article', 'Certificate as reviewer', 'LNU special order as reviewer']],
    ['Involvement in completed student theses', 'Adviser', 3, ['Approval sheet', 'Special order']],
    ['Involvement in completed student theses', 'Statistician', 3, ['Approval sheet', 'Special order']],
    ['Involvement in completed student theses', 'Panel', 3, ['Approval sheet', 'Special order']],
    ['Involvement in completed student theses', 'Editor', 3, ['Approval sheet', 'Special order']],
    ['Involvement in completed student theses', 'Instrument validator / evaluator', 3, ['Approval sheet', 'Special order']],
    ['Involvement in completed student theses', 'Secretary', 3, ['Secretary: proceedings if possible, special order, or certification from the dean']],
    ['Involvement in proposal hearing', 'Adviser', 3, ['Special order']],
    ['Involvement in proposal hearing', 'Statistician', 3, ['Special order']],
    ['Involvement in proposal hearing', 'Panel', 3, ['Special order']],
    ['Involvement in proposal hearing', 'Secretary', 3, ['Secretary: proceedings if possible, special order, or certification from the dean']],
    ['Funded research involvement', 'Statistician', 1, ['Certification as statistician']],
    ['Funded research involvement', 'Panel of concept', 1, ['Signed documentation of proceedings']],
    ['Funded research involvement', 'Panel of completed', 1, ['Approval sheet']],
    ['Funded research involvement', 'Program/project leader', 1, ['Special order as project leader', 'Certification as project leader']],
    ['Funded research involvement', 'Program/project member', 1, ['Special order as project member', 'Certification as project member']],
    ['Funded research involvement', 'Data gatherer', 1, ['Special order as data gatherer or enumerator', 'Certification as data gatherer or enumerator']],
    ['Funded research involvement', 'Editor', 1, ['Special order as editor', 'Certification as editor']],
    ['Funded research involvement', 'Data encoder', 1, ['Special order as data encoder or tabulator', 'Certification as data encoder or tabulator']],
    ['Funded research involvement', 'Compiler', 1, ['Special order as compiler, binder, or collator', 'Certification as compiler, binder, or collator']],
]);

it('adds defaults only to unconfigured original types and remains idempotent', function () {
    $unconfigured = ResearchType::query()->where('name', 'On-going research')->firstOrFail();
    $configured = ResearchType::query()->where('name', 'On-going collaborative research')->firstOrFail();
    $unconfigured->evidenceRequirements()->delete();
    $configured->evidenceRequirements()->delete();
    $existing = $configured->evidenceRequirements()->create(['name' => 'Administrator decision', 'is_required' => true, 'is_active' => false]);
    $custom = ResearchType::query()->create(['slug' => 'custom_ongoing', 'name' => $unconfigured->name, 'category' => $unconfigured->category, 'is_active' => true, 'sort_order' => 999]);
    $unconfigured->update(['name' => 'Renamed by administrator', 'category' => 'Changed category']);
    $before = DB::table('research_evidence_requirements')->count();

    brochureEvidenceMigration()->up();
    brochureEvidenceMigration()->up();

    expect($unconfigured->evidenceRequirements()->orderBy('id')->pluck('name')->all())->toBe(['Work plan', 'Accomplishment report']);
    expect($configured->evidenceRequirements()->pluck('id')->all())->toBe([$existing->id]);
    expect($existing->fresh()->name)->toBe('Administrator decision')->and($existing->fresh()->is_active)->toBeFalse()->and($existing->fresh()->is_required)->toBeTrue();
    expect($custom->evidenceRequirements()->count())->toBe(0);
    expect(DB::table('research_evidence_requirements')->count())->toBe($before + 2);
});

it('retains defaults and administrator changes on rollback', function () {
    $requirement = ResearchType::query()->where('name', 'On-going research')->firstOrFail()->evidenceRequirements()->firstOrFail();
    $requirement->update(['name' => 'Reviewed evidence', 'is_required' => true]);
    $before = DB::table('research_evidence_requirements')->count();

    brochureEvidenceMigration()->down();

    expect($requirement->fresh()->name)->toBe('Reviewed evidence')->and($requirement->fresh()->is_required)->toBeTrue();
    expect(DB::table('research_evidence_requirements')->count())->toBe($before);
});

it('permits incomplete optional evidence and preserves used defaults and snapshots on rollback', function () {
    Storage::fake('local');
    $faculty = User::factory()->faculty()->create();
    $year = AcademicYear::query()->create(['name' => 'Brochure year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    $type = ResearchType::query()->where('name', 'On-going research')->firstOrFail();
    $type->update(['has_authors' => false]);
    $requirement = $type->evidenceRequirements()->where('name', 'Work plan')->firstOrFail();

    $this->actingAs($faculty)->post(route('faculty.submissions.store'), [
        'title' => 'Brochure evidence submission',
        'research_type' => $type->slug,
        'academic_year_id' => $year->id,
        'submit_now' => true,
        'attachments' => [UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')],
        'attachment_requirements' => [$requirement->id],
    ])->assertSessionHasNoErrors();

    $submission = ResearchSubmission::query()->where('title', 'Brochure evidence submission')->firstOrFail();
    $history = $submission->statusHistories()->where('action', 'submitted')->firstOrFail();
    $snapshot = $history->meta['evidence_requirements'];
    expect($snapshot)->toHaveCount(2);
    expect($snapshot[0]['name'])->toBe('Work plan')->and($snapshot[0]['attachments'][0]['name'])->toBe('plan.pdf');
    expect($snapshot[1]['name'])->toBe('Accomplishment report')->and($snapshot[1]['attachments'])->toBeEmpty();

    $requirement->update(['name' => 'Reviewed plan', 'is_required' => true]);
    brochureEvidenceMigration()->down();

    expect($history->fresh()->meta['evidence_requirements'])->toBe($snapshot);
    expect($submission->attachments()->firstOrFail()->research_evidence_requirement_id)->toBe($requirement->id);
    expect($requirement->fresh()->name)->toBe('Reviewed plan');
});
