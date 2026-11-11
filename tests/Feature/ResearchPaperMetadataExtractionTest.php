<?php

use App\Models\User;
use App\Services\ResearchPaperMetadataExtractor;
use Illuminate\Http\UploadedFile;

it('suggests paper title authors abstract DOI and publication date from extracted text', function () {
    $result = (new ResearchPaperMetadataExtractor)->parseText(<<<'PAPER'
Evaluating Community-Based Learning in Rural Schools
Maria Santos; Jose Cruz
Abstract
This study evaluates a community-based learning program across rural schools. The results show improved student participation and stronger partnerships between families and teachers.
Keywords: community learning, education
DOI: 10.1234/example.2024.10
Published: 2024-04-12
PAPER);

    expect($result['fields'])
        ->toHaveKey('title', 'Evaluating Community-Based Learning in Rural Schools')
        ->toHaveKey('authors', ['Maria Santos', 'Jose Cruz'])
        ->toHaveKey('doi', '10.1234/example.2024.10')
        ->toHaveKey('publication_date', '2024-04-12')
        ->toHaveKey('keywords', ['community learning', 'education'])
        ->toHaveKey('abstract')
        ->and($result['extracted_fields'])->toHaveCount(6);
});

it('only extracts author names from the title block instead of citation authors', function () {
    $result = (new ResearchPaperMetadataExtractor)->parseText(<<<'PAPER'
Improving Community Health Through Rural Outreach
Maria Santos; Jose Cruz
Department of Public Health
Leyte Normal University
Tacloban City, Philippines
Corresponding author: maria@example.edu
Abstract
This study evaluates outreach programs and their impact on health outcomes across rural communities.
Introduction
The topic has been studied widely.
References
Authors: Citation Writer; Referenced Researcher
PAPER);

    expect($result['fields']['authors'])
        ->toBe(['Maria Santos', 'Jose Cruz']);
});

it('extracts labeled key words without inventing keywords when none are labeled', function () {
    $extractor = new ResearchPaperMetadataExtractor;

    $withKeywords = $extractor->parseText("A Study of Rural Education\nKey words - learning; schools; learning.");
    $withoutKeywords = $extractor->parseText("A Study of Rural Education\nThis paper discusses learning and schools.");

    expect($withKeywords['fields']['keywords'])->toBe(['learning', 'schools'])
        ->and($withoutKeywords['fields'])->not->toHaveKey('keywords');
});

it('extracts names from an authors label directly beneath the title', function () {
    $result = (new ResearchPaperMetadataExtractor)->parseText(<<<'PAPER'
Renewable Energy Adoption in Coastal Communities
Authors: Ana Reyes; Ben Garcia
Abstract
This article examines how renewable energy use affects participating coastal households and local community outcomes.
PAPER);

    expect($result['fields']['authors'])
        ->toBe(['Ana Reyes', 'Ben Garcia']);
});

it('splits comma-separated authors immediately below the research title', function () {
    $result = (new ResearchPaperMetadataExtractor)->parseText(<<<'PAPER'
Research Title: Sustainable Farming in Eastern Visayas
Lourdes A. Santos1, Mark B. Reyes2, Nina P. Cruz3
1 Leyte Normal University; 2 Eastern Visayas State University
Abstract
This study describes sustainable farming practices and measures their effect on rural household income and crop productivity.
PAPER);

    expect($result['fields']['title'] ?? null)
        ->toBe('Sustainable Farming in Eastern Visayas')
        ->and($result['fields']['authors'] ?? [])
        ->toBe(['Lourdes A. Santos', 'Mark B. Reyes', 'Nina P. Cruz']);
});

it('does not treat the research title as an author when the paper starts with an institutional masthead', function () {
    $result = (new ResearchPaperMetadataExtractor)->parseText(<<<'PAPER'
LEYTE NORMAL UNIVERSITY
OFFICE OF RESEARCH AND INNOVATION
The Effects of Blended Learning on Student Achievement
Authors: Ana Reyes; Ben Garcia
Leyte Normal University
Abstract
This study examines measurable changes in student achievement after blended learning was introduced.
PAPER);

    expect($result['fields']['title'] ?? null)
        ->toBe('The Effects of Blended Learning on Student Achievement')
        ->and($result['fields']['authors'] ?? [])
        ->toBe(['Ana Reyes', 'Ben Garcia']);
});

it('rejects unsupported file types for paper extraction', function () {
    $faculty = User::factory()->faculty()->create();

    $this->actingAs($faculty)
        ->postJson(route('faculty.submissions.extract-metadata'), [
            'paper' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('paper');
});

it('allows active staff to extract metadata for personal submissions', function (string $role) {
    $staff = User::factory()->{$role}()->create();
    $this->mock(ResearchPaperMetadataExtractor::class)
        ->shouldReceive('extract')->once()->andReturn(['fields' => ['title' => 'Suggested title'], 'extracted_fields' => ['title']]);

    $this->actingAs($staff)
        ->postJson(route('faculty.submissions.extract-metadata'), [
            'paper' => UploadedFile::fake()->createWithContent('paper.pdf', '%PDF-1.4'),
        ])
        ->assertOk()->assertJsonPath('fields.title', 'Suggested title');
})->with(['coordinator', 'admin']);

it('forbids inactive staff from using paper extraction', function () {
    $staff = User::factory()->coordinator()->create(['is_active' => false]);

    $this->actingAs($staff)
        ->postJson(route('faculty.submissions.extract-metadata'), [
            'paper' => UploadedFile::fake()->createWithContent('paper.pdf', '%PDF-1.4'),
        ])->assertForbidden();
});
