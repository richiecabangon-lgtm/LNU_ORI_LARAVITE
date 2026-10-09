<?php

use App\Models\ResearchAgenda;
use App\Models\Sdg;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets admins upload catalog artwork that appears in faculty form metadata', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $sdg = Sdg::query()->where('number', 4)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.research-agendas.store'), [
            'name' => 'Community Research',
            'description' => 'Local engagement',
            'image' => UploadedFile::fake()->image('agenda.png'),
        ])
        ->assertRedirect();

    $agenda = ResearchAgenda::query()->where('name', 'Community Research')->firstOrFail();
    Storage::disk('public')->assertExists($agenda->image_path);
    expect($agenda->image_url)->toBe('/storage/'.$agenda->image_path);

    $this->put(route('admin.sdgs.update', $sdg), [
        'number' => $sdg->number,
        'name' => $sdg->name,
        'description' => $sdg->description,
        'color' => $sdg->color,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('sdg.png'),
    ])->assertRedirect();

    $sdg->refresh();
    Storage::disk('public')->assertExists($sdg->image_path);
    expect($sdg->image_url)->toBe('/storage/'.$sdg->image_path);

    $faculty = User::factory()->faculty()->create();
    $this->actingAs($faculty)
        ->get(route('faculty.submissions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sdgs.3.image_url', '/storage/'.$sdg->image_path)
            ->where('researchAgendas.0.image_url', '/storage/'.$agenda->image_path));
});

it('allows only admins to change catalog images', function () {
    Storage::fake('public');
    $faculty = User::factory()->faculty()->create();
    $sdg = Sdg::query()->where('number', 4)->firstOrFail();

    $this->actingAs($faculty)
        ->put(route('admin.sdgs.update', $sdg), [
            'number' => $sdg->number,
            'name' => $sdg->name,
            'description' => $sdg->description,
            'color' => $sdg->color,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('sdg.png'),
        ])
        ->assertForbidden();
});
