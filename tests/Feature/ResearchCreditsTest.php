<?php

use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\ResearchAgenda;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use App\Models\Sdg;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function makeResearchCreditFixture(): array
{
    $academicYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'is_current' => true,
        'is_active' => true,
    ]);
    $submitter = User::factory()->faculty()->create(['name' => 'Lead Author']);
    $coauthor = User::factory()->faculty()->create(['name' => 'Second Author']);
    $sdg = Sdg::query()->where('number', 4)->firstOrFail();
    $agenda = ResearchAgenda::query()->create([
        'name' => 'Faculty Research Development',
        'description' => 'Research development and mentoring for faculty members.',
        'is_active' => true,
    ]);
    ResearchType::withTrashed()->whereIn('slug', ['journal_article', 'conference_paper'])->update(['is_active' => true, 'deleted_at' => null]);
    ResearchTypePointSetting::query()->updateOrCreate(['research_type' => 'journal_article'], [
        'points_per_output' => 100,
    ]);
    ResearchTypePointSetting::query()->updateOrCreate(['research_type' => 'conference_paper'], [
        'points_per_output' => 80,
    ]);

    return compact('academicYear', 'submitter', 'coauthor', 'sdg', 'agenda');
}

function researchCreditPayload(array $fixture, string $type = 'journal_article', string $title = 'A Unique Research Title'): array
{
    $total = $type === 'conference_paper' ? 80 : 100;

    return [
        'title' => $title,
        'research_type' => $type,
        'academic_year_id' => $fixture['academicYear']->id,
        'sdgs' => [$fixture['sdg']->id],
        'research_agendas' => [$fixture['agenda']->id],
        'abstract' => 'Research abstract text.',
        'authors' => [$fixture['submitter']->name],
        'author_details' => [[
            'name' => $fixture['submitter']->name,
            'email' => $fixture['submitter']->email,
            'institution' => 'Leyte Normal University',
            'allocated_points' => $total,
        ]],
        'faculty_role_rank' => 1,
        'claimed_points' => 9999,
        'submit_now' => true,
    ];
}

it('lets only admins change the research-type point schedule', function () {
    $coordinator = User::factory()->coordinator()->create();
    $admin = User::factory()->admin()->create();
    $slug = ResearchType::query()->where('name', 'Patent (if granted)')->value('slug');

    $this->actingAs($coordinator)
        ->put(route('admin.research-type-points.update'), ['points' => [$slug => 50]])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('admin.research-type-points.update'), ['points' => [$slug => 125]])
        ->assertRedirect();

    expect((float) ResearchTypePointSetting::query()->where('research_type', $slug)->value('points_per_output'))
        ->toBe(125.0);
});

it('scopes coordinator research profiles to their department and allows admins to browse all faculty', function () {
    $firstCollege = College::query()->create(['code' => 'P1', 'name' => 'Profile College One']);
    $secondCollege = College::query()->create(['code' => 'P2', 'name' => 'Profile College Two']);
    $firstDepartment = Department::query()->create(['college_id' => $firstCollege->id, 'code' => 'D1', 'name' => 'Profile Department One']);
    $secondDepartment = Department::query()->create(['college_id' => $secondCollege->id, 'code' => 'D2', 'name' => 'Profile Department Two']);
    User::factory()->faculty()->create(['department_id' => $firstDepartment->id, 'college_id' => $firstCollege->id]);
    User::factory()->faculty()->create(['department_id' => $secondDepartment->id, 'college_id' => $secondCollege->id]);
    $coordinator = User::factory()->coordinator()->create(['department_id' => $firstDepartment->id]);

    $this->actingAs($coordinator)
        ->get(route('research-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('faculty.data', fn ($faculty) => count($faculty) === 1
                && $faculty[0]['department_id'] === $firstDepartment->id));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get(route('research-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('faculty.data', fn ($faculty) => count($faculty) === 2));
});

it('includes scholarly profile destinations on the faculty research profile', function () {
    $faculty = User::factory()->faculty()->create([
        'academic_rank' => 'Associate Professor',
        'orcid_url' => 'https://orcid.org/0000-0000-0000-0001',
        'google_scholar_url' => 'https://scholar.google.com/citations?user=faculty-profile',
        'institutional_profile_url' => 'https://www.lnu.edu.ph/faculty/researcher',
    ]);

    $this->actingAs($faculty)
        ->get(route('research-profiles.show', $faculty->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('faculty.academic_rank', 'Associate Professor')
            ->where('faculty.orcid_url', 'https://orcid.org/0000-0000-0000-0001')
            ->where('faculty.google_scholar_url', 'https://scholar.google.com/citations?user=faculty-profile')
            ->where('faculty.institutional_profile_url', 'https://www.lnu.edu.ph/faculty/researcher'));
});

it('provides agenda descriptions in the faculty submission choices', function () {
    $fixture = makeResearchCreditFixture();

    $this->actingAs($fixture['submitter'])
        ->get(route('faculty.submissions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('researchAgendas.0.name', 'Faculty Research Development')
            ->where('researchAgendas.0.description', 'Research development and mentoring for faculty members.'));
});

it('uses admin points and links coauthors to one shared submission', function () {
    Storage::fake('local');
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);
    $payload['authors'] = [$fixture['submitter']->name, $fixture['coauthor']->name];
    $payload['author_details'] = [
        [
            'name' => $fixture['submitter']->name,
            'email' => $fixture['submitter']->email,
            'institution' => 'Leyte Normal University',
            'allocated_points' => 60,
        ],
        [
            'name' => $fixture['coauthor']->name,
            'institution' => 'Eastern Visayas State University',
            'allocated_points' => 40,
            'authorization_file' => UploadedFile::fake()->create('coauthor-consent.pdf', 20, 'application/pdf'),
        ],
    ];

    $this->actingAs($fixture['submitter'])
        ->post(route('faculty.submissions.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success', 'Research involvement submitted for evaluation.');

    $submission = ResearchSubmission::query()->where('title', $payload['title'])->firstOrFail();
    $allocations = $submission->authorshipAllocations()->orderBy('author_order')->get();

    expect((float) $submission->claimed_points)->toBe(100.0)
        ->and((float) $allocations->sum('allocated_points'))->toBe(100.0)
        ->and($allocations[1]->faculty_id)->toBe($fixture['coauthor']->id)
        ->and($allocations[1]->authorization_path)->not->toBeNull();
    expect($allocations[1]->toArray())
        ->toHaveKey('has_authorization', true)
        ->not->toHaveKey('authorization_path');
    Storage::disk('local')->assertExists($allocations[1]->authorization_path);

    $this->actingAs($fixture['coauthor'])
        ->get(route('faculty.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.data', fn ($records) => count($records) === 1
                && $records[0]['uuid'] === $submission->uuid));

    $this->get(route('faculty.submissions.show', $submission->uuid))->assertOk();
    $this->get(route('research-profiles.show', $fixture['coauthor']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions', fn ($records) => $records->count() === 1
                && (float) $records->first()['faculty_points'] === 40.0));
    $this->actingAs($fixture['submitter'])
        ->get(route('research-authorizations.download', $allocations[1]->id))
        ->assertOk();
    $this->actingAs($fixture['coauthor'])
        ->get(route('research-authorizations.download', $allocations[1]->id))
        ->assertForbidden();
    $this->put(route('faculty.submissions.update', $submission->uuid), [])->assertForbidden();

    $unrelatedFaculty = User::factory()->faculty()->create();
    $this->actingAs($unrelatedFaculty)
        ->get(route('research-profiles.show', $fixture['coauthor']->id))
        ->assertForbidden();
});

it('requires proof when a coauthor receives less than the equal share', function () {
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);
    $payload['authors'] = [$fixture['submitter']->name, $fixture['coauthor']->name];
    $payload['author_details'] = [
        ['name' => $fixture['submitter']->name, 'email' => $fixture['submitter']->email, 'allocated_points' => 60],
        ['name' => $fixture['coauthor']->name, 'email' => $fixture['coauthor']->email, 'allocated_points' => 40],
    ];

    $this->actingAs($fixture['submitter'])
        ->from(route('faculty.submissions.create'))
        ->post(route('faculty.submissions.store'), $payload)
        ->assertRedirect(route('faculty.submissions.create'))
        ->assertSessionHasErrors('author_details');

    expect(ResearchSubmission::query()->count())->toBe(0);

    $payload['title'] = 'Lead Author Reduced Share Requires Consent';
    $payload['author_details'][0]['allocated_points'] = 40;
    $payload['author_details'][1]['allocated_points'] = 60;
    $payload['author_details'][1]['authorization_file'] = null;

    $this->from(route('faculty.submissions.create'))
        ->post(route('faculty.submissions.store'), $payload)
        ->assertRedirect(route('faculty.submissions.create'))
        ->assertSessionHasErrors('author_details');

    expect(ResearchSubmission::query()->count())->toBe(0);
});

it('returns a visible validation error when a required submission title is missing', function () {
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);
    $payload['title'] = '';

    $this->actingAs($fixture['submitter'])
        ->from(route('faculty.submissions.create'))
        ->post(route('faculty.submissions.store'), $payload)
        ->assertRedirect(route('faculty.submissions.create'))
        ->assertSessionHasErrors('title');

    expect(ResearchSubmission::query()->count())->toBe(0);
});

it('blocks same-type duplicate titles but permits the title under a different research type', function () {
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);

    $this->actingAs($fixture['submitter'])->post(route('faculty.submissions.store'), $payload)->assertRedirect();

    $this->from(route('faculty.submissions.create'))
        ->post(route('faculty.submissions.store'), $payload)
        ->assertRedirect(route('faculty.submissions.create'))
        ->assertSessionHasErrors('title');

    $differentTypePayload = researchCreditPayload($fixture, 'conference_paper', $payload['title']);
    $this->post(route('faculty.submissions.store'), $differentTypePayload)->assertRedirect();

    expect(ResearchSubmission::query()->where('title', $payload['title'])->count())->toBe(2);
});

it('distributes the full configured total equally when author shares are not customized', function () {
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);
    $payload['authors'] = [$fixture['submitter']->name, $fixture['coauthor']->name];
    $payload['author_details'] = [
        ['name' => $fixture['submitter']->name, 'email' => $fixture['submitter']->email, 'allocated_points' => 50],
        ['name' => $fixture['coauthor']->name, 'email' => $fixture['coauthor']->email, 'allocated_points' => 50],
    ];

    $this->actingAs($fixture['submitter'])->post(route('faculty.submissions.store'), $payload)->assertRedirect();

    $submission = ResearchSubmission::query()->firstOrFail();
    expect((float) $submission->authorshipAllocations()->sum('allocated_points'))->toBe(100.0);
});

it('credits only the submitting faculty for role-based involvement without authors', function () {
    $fixture = makeResearchCreditFixture();
    $slug = ResearchType::query()->where('name', 'Referee - International journal (per abstract)')->value('slug');
    $payload = researchCreditPayload($fixture, $slug, 'Abstract reviewed for a journal');
    unset($payload['authors'], $payload['author_details']);

    $this->actingAs($fixture['submitter'])
        ->post(route('faculty.submissions.store'), $payload)
        ->assertSessionHasNoErrors();

    $submission = ResearchSubmission::query()->where('title', 'Abstract reviewed for a journal')->firstOrFail();
    $allocations = $submission->authorshipAllocations;

    expect($allocations)->toHaveCount(1)
        ->and($allocations->first()->faculty_id)->toBe($fixture['submitter']->id)
        ->and((float) $allocations->first()->allocated_points)->toBe(10.0);

    $this->actingAs($fixture['submitter'])
        ->get(route('faculty.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.has_authors', false));
});

it('still requires authors for authored research types', function () {
    $fixture = makeResearchCreditFixture();
    $payload = researchCreditPayload($fixture);
    unset($payload['authors'], $payload['author_details']);

    $this->actingAs($fixture['submitter'])
        ->post(route('faculty.submissions.store'), $payload)
        ->assertSessionHasErrors(['authors', 'author_details']);
});

it('accepts up to twenty evidence attachments and rejects more', function () {
    Storage::fake('public');
    $fixture = makeResearchCreditFixture();
    $files = fn (int $count): array => collect(range(1, $count))
        ->map(fn (int $number) => UploadedFile::fake()->create("evidence-{$number}.pdf", 20, 'application/pdf'))->all();

    $payload = researchCreditPayload($fixture, 'journal_article', 'Twenty files');
    $this->actingAs($fixture['submitter'])->post(route('faculty.submissions.store'), [...$payload, 'attachments' => $files(20)])
        ->assertSessionHasNoErrors();
    expect(ResearchSubmission::query()->where('title', 'Twenty files')->firstOrFail()->attachments)->toHaveCount(20);

    $this->actingAs($fixture['submitter'])->post(route('faculty.submissions.store'), [...researchCreditPayload($fixture, 'journal_article', 'Too many'), 'attachments' => $files(21)])
        ->assertSessionHasErrors('attachments');
});
