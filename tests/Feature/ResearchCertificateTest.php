<?php

use App\Enums\SubmissionStatus;
use App\Models\AcademicYear;
use App\Models\CertificationSetting;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function certificateSubmission(User $owner, SubmissionStatus $status = SubmissionStatus::Approved): ResearchSubmission
{
    $year = AcademicYear::query()->create([
        'name' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'is_current' => true, 'is_active' => true,
    ]);

    return ResearchSubmission::query()->create([
        'faculty_id' => $owner->id, 'academic_year_id' => $year->id,
        'title' => 'Research <script>alert(1)</script>', 'research_type' => 'published_research_published_individual_research_scopus_indexed_journal',
        'authors' => [$owner->name], 'status' => $status, 'approved_at' => '2026-11-09 12:00:00',
        'indexing' => 'Scopus <img src=x onerror=alert(1)>',
    ]);
}

it('provides escaped printable and downloadable certificates to the owner and linked coauthor', function () {
    $owner = User::factory()->faculty()->create(['name' => 'Owner <script>alert(2)</script>']);
    $coauthor = User::factory()->faculty()->create(['name' => 'Coauthor']);
    $submission = certificateSubmission($owner);
    $submission->authorshipAllocations()->create([
        'faculty_id' => $coauthor->id, 'name' => $coauthor->name, 'author_order' => 1, 'allocated_points' => 0,
    ]);
    CertificationSetting::query()->create([
        'institution_name' => 'University <script>alert(3)</script>', 'office_name' => 'Research Office',
        'heading' => 'Research Certificate', 'additional_text' => '<script>alert(4)</script>',
        'first_signatory_name' => 'Signer <script>alert(5)</script>', 'first_signatory_title' => 'Director',
    ]);

    $response = $this->actingAs($owner)->get(route('faculty.certificates.show', $submission));
    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('November 9, 2026')
        ->assertSee($submission->reference_code)->assertSee('Indexing (self-reported)')
        ->assertSee('Print certificate')
        ->assertSee('data:image/png;base64,', false)
        ->assertSee('size: A4 portrait', false)
        ->assertSee("12pt 'Times New Roman'", false)
        ->assertSee('not official Scopus verification')
        ->assertSee('Research &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('Owner &lt;script&gt;alert(2)&lt;/script&gt;', false)
        ->assertSee('University &lt;script&gt;alert(3)&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;alert(4)&lt;/script&gt;', false)
        ->assertSee('Signer &lt;script&gt;alert(5)&lt;/script&gt;', false)
        ->assertSee('Scopus &lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<script>alert', false)->assertDontSee('<img src=x', false);

    $download = $this->actingAs($coauthor)->get(route('faculty.certificates.download', $submission));
    $download->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')->assertSee('Coauthor');
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment;')->toEndWith('.html"');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'nonce-");
    expect($response->headers->get('Content-Security-Policy'))->toContain('img-src data:');
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('can.viewCertificate', true));
});

it('rejects every non-approved status on both certificate endpoints', function (SubmissionStatus $status) {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner, $status);

    foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
        $this->actingAs($owner)->get(route($routeName, $submission))->assertForbidden();
    }
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('can.viewCertificate', false));
})->with(array_values(array_filter(SubmissionStatus::cases(), fn (SubmissionStatus $status) => $status !== SubmissionStatus::Approved)));

it('rejects unrelated faculty, inactive participants, administrators, coordinators and guests', function () {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $unrelated = User::factory()->faculty()->create();
    $inactive = User::factory()->faculty()->create(['is_active' => false]);
    $submission->authorshipAllocations()->create([
        'faculty_id' => $inactive->id, 'name' => $inactive->name, 'author_order' => 1, 'allocated_points' => 0,
    ]);

    foreach ([$unrelated, $inactive, User::factory()->admin()->create(), User::factory()->coordinator()->create()] as $user) {
        foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
            $this->actingAs($user)->get(route($routeName, $submission))->assertForbidden();
        }
    }
    auth()->logout();
    $this->get(route('faculty.certificates.show', $submission))->assertRedirect(route('login'));
    $this->get(route('faculty.certificates.download', $submission))->assertRedirect(route('login'));
});

it('lets only active admins configure and validate certificate settings', function () {
    $admin = User::factory()->admin()->create();
    $faculty = User::factory()->faculty()->create();
    $coordinator = User::factory()->coordinator()->create();
    $payload = [
        'institution_name' => 'University', 'office_name' => 'Research Office', 'heading' => 'Certificate',
        'additional_text' => 'Additional record text', 'first_signatory_name' => 'First Signer',
        'first_signatory_title' => 'Director', 'second_signatory_name' => 'Second Signer',
        'second_signatory_title' => 'President',
    ];

    foreach ([$faculty, $coordinator, User::factory()->admin()->create(['is_active' => false])] as $user) {
        $this->actingAs($user)->get(route('admin.certification.settings'))->assertForbidden();
        $this->put(route('admin.certification.settings.update'), $payload)->assertForbidden();
    }
    $this->actingAs($admin)->put(route('admin.certification.settings.update'), [
        ...$payload, 'institution_name' => '', 'additional_text' => str_repeat('x', 2001),
    ])->assertSessionHasErrors(['institution_name', 'additional_text']);
    $this->put(route('admin.certification.settings.update'), [...$payload, 'allow_non_scopus' => 'yes'])
        ->assertSessionHasErrors(['allow_non_scopus']);
    $this->assertDatabaseCount('certification_settings', 0);
    $this->put(route('admin.certification.settings.update'), $payload)->assertRedirect();
    $this->assertDatabaseHas('certification_settings', ['id' => 1, ...$payload]);
    $this->get(route('admin.certification.settings'))->assertInertia(fn (Assert $page) => $page->where('settings.heading', 'Certificate'));
    $submission = certificateSubmission($faculty);
    $this->actingAs($faculty)->get(route('faculty.certificates.show', $submission))
        ->assertSee('University')->assertSee('Additional record text')->assertSee('First Signer')->assertSee('Second Signer');
});

it('omits indexing when no indexing was reported', function () {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $submission->update(['indexing' => null]);

    $this->actingAs($owner)->get(route('faculty.certificates.show', $submission))
        ->assertOk()->assertDontSee('Indexing (self-reported)')->assertSee('not official Scopus verification');
});

it('denies certificates by default for approved non-Scopus submissions', function () {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $submission->update(['research_type' => 'journal_article', 'indexing' => null]);

    $this->actingAs($owner)->get(route('faculty.certificates.show', $submission))->assertForbidden();
    $this->get(route('faculty.certificates.download', $submission))->assertForbidden();
});

it('does not infer Scopus journal eligibility from free text or other Scopus categories', function (string $researchType, string $indexing) {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $submission->update(['research_type' => $researchType, 'indexing' => $indexing, 'title' => 'Scopus-indexed journal research']);

    foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
        $this->actingAs($owner)->get(route($routeName, $submission))->assertForbidden();
    }
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('can.viewCertificate', false));
})->with([
    'legacy journal with exact indexing' => ['journal_article', 'Scopus'],
    'misleading non-Scopus text' => ['journal_article', 'non-Scopus'],
    'citation' => ['research_citations_citation_in_a_scopus_journal_per_citation', 'Scopus'],
    'refereeing' => ['refereeing_in_a_peer_reviewed_journal_referee_isi_indexed_scopus_jour_fd246012', 'Scopus'],
]);

it('uses the dedicated published Scopus journal catalogue type', function () {
    $this->assertDatabaseHas('research_types', [
        'slug' => 'published_research_published_individual_research_scopus_indexed_journal',
        'category' => 'Published research',
        'name' => 'Published individual research - Scopus-indexed journal',
    ]);
});

it('defaults the settings to disabled for new and existing records', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.certification.settings'))
        ->assertInertia(fn (Assert $page) => $page->where('settings.allow_non_scopus', false));

    $migration = require database_path('migrations/2026_11_10_044247_add_allow_non_scopus_to_certification_settings_table.php');
    $migration->down();
    DB::table('certification_settings')->insert([
        'id' => 1, 'institution_name' => 'University', 'office_name' => 'Research Office', 'heading' => 'Certificate',
    ]);
    $migration->up();

    $this->assertDatabaseHas('certification_settings', ['id' => 1, 'allow_non_scopus' => false]);
    $this->get(route('admin.certification.settings'))
        ->assertInertia(fn (Assert $page) => $page->where('settings.allow_non_scopus', false));
});

it('allows administrators to enable and later disable certificates for other approved research types', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->faculty()->create();
    $coauthor = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $submission->update(['research_type' => 'journal_article', 'indexing' => 'Scopus']);
    $submission->authorshipAllocations()->create([
        'faculty_id' => $coauthor->id, 'name' => $coauthor->name, 'author_order' => 1, 'allocated_points' => 0,
    ]);
    $payload = [
        'institution_name' => 'University', 'office_name' => 'Research Office', 'heading' => 'Certificate',
        'allow_non_scopus' => true,
    ];

    $this->actingAs($admin)->put(route('admin.certification.settings.update'), $payload)->assertRedirect();
    $this->assertDatabaseHas('certification_settings', ['id' => 1, 'allow_non_scopus' => true]);
    $this->get(route('admin.certification.settings'))->assertInertia(fn (Assert $page) => $page->where('settings.allow_non_scopus', true));
    foreach ([$owner, $coauthor] as $participant) {
        foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
            $this->actingAs($participant)->get(route($routeName, $submission))->assertOk();
        }
        $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('can.viewCertificate', true));
    }

    $this->actingAs($admin)->put(route('admin.certification.settings.update'), [...$payload, 'allow_non_scopus' => false])->assertRedirect();
    $this->assertDatabaseHas('certification_settings', ['id' => 1, 'allow_non_scopus' => false]);
    foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
        $this->actingAs($owner)->get(route($routeName, $submission))->assertForbidden();
    }
    $this->get(route('faculty.submissions.show', $submission))->assertInertia(fn (Assert $page) => $page->where('can.viewCertificate', false));
});

it('keeps approval and participant restrictions when other research types are enabled', function () {
    $owner = User::factory()->faculty()->create();
    $submission = certificateSubmission($owner);
    $submission->update(['research_type' => 'journal_article']);
    CertificationSetting::query()->create(['allow_non_scopus' => true]);
    $inactive = User::factory()->faculty()->create(['is_active' => false]);
    $submission->authorshipAllocations()->create([
        'faculty_id' => $inactive->id, 'name' => $inactive->name, 'author_order' => 1, 'allocated_points' => 0,
    ]);

    foreach ([$inactive, User::factory()->faculty()->create(), User::factory()->admin()->create(), User::factory()->coordinator()->create()] as $user) {
        foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
            $this->actingAs($user)->get(route($routeName, $submission))->assertForbidden();
        }
    }
    foreach (SubmissionStatus::cases() as $status) {
        if ($status === SubmissionStatus::Approved) {
            continue;
        }
        $submission->update(['status' => $status]);
        foreach (['faculty.certificates.show', 'faculty.certificates.download'] as $routeName) {
            $this->actingAs($owner)->get(route($routeName, $submission))->assertForbidden();
        }
    }
    auth()->logout();
    $this->get(route('faculty.certificates.show', $submission))->assertRedirect(route('login'));
    $this->get(route('faculty.certificates.download', $submission))->assertRedirect(route('login'));
});
