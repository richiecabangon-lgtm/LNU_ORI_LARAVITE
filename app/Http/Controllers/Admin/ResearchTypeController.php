<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResearchTypeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/ResearchTypes/Index', [
            'researchTypes' => ResearchType::query()->withTrashed()->with(['pointSetting', 'evidenceRequirements' => fn ($query) => $query->where('is_active', true)->orderBy('id')])->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (ResearchType $type): array => [
                    'id' => $type->id,
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'description' => $type->description,
                    'category' => $type->category,
                    'has_authors' => $type->has_authors,
                    'points' => (float) ($type->pointSetting?->points_per_output ?? 0),
                    'is_active' => $type->is_active && $type->deleted_at === null,
                    'submission_count' => ResearchSubmission::query()->where('research_type', $type->slug)->count(),
                    'requirements' => $type->evidenceRequirements,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:80'],
            'category' => ['nullable', 'string', 'max:100'],
            'has_authors' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name'], '_');
        if (ResearchType::withTrashed()->where('slug', $slug)->exists()) {
            return back()->withErrors(['slug' => 'This key has already been used and is reserved for historical records.']);
        }

        $type = ResearchType::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'has_authors' => $data['has_authors'] ?? true,
            'sort_order' => (int) ResearchType::withTrashed()->max('sort_order') + 1,
            'updated_by' => $request->user()->id,
            'is_active' => true,
        ]);

        ResearchTypePointSetting::query()->create([
            'research_type' => $type->slug,
            'points_per_output' => $data['points'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Research involvement type added.');
    }

    public function update(Request $request, int $researchType): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $researchType = ResearchType::withTrashed()->findOrFail($researchType);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'has_authors' => ['sometimes', 'boolean'],
            'points' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['required', 'boolean'],
            'requirements' => ['sometimes', 'array', 'max:50'],
            'requirements.*.id' => ['nullable', 'integer', 'distinct'],
            'requirements.*.name' => ['required', 'string', 'max:255'],
            'requirements.*.is_required' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($researchType, $data, $request) {
            $researchType = ResearchType::withTrashed()->lockForUpdate()->findOrFail($researchType->id);
            if (array_key_exists('requirements', $data)) {
                $existingIds = $researchType->evidenceRequirements()->where('is_active', true)->pluck('id');
                $submittedIds = collect($data['requirements'])->pluck('id')->filter();
                if ($submittedIds->diff($existingIds)->isNotEmpty()) {
                    throw ValidationException::withMessages(['requirements' => 'A requirement does not belong to this research type.']);
                }
                $researchType->evidenceRequirements()->whereNotIn('id', $submittedIds)->update(['is_active' => false]);
                foreach ($data['requirements'] as $requirement) {
                    if (! empty($requirement['id'])) {
                        $researchType->evidenceRequirements()->whereKey($requirement['id'])->update([
                            'name' => $requirement['name'], 'is_required' => $requirement['is_required'],
                        ]);
                    } else {
                        $researchType->evidenceRequirements()->create([
                            'name' => $requirement['name'], 'is_required' => $requirement['is_required'],
                        ]);
                    }
                }
            }
            $researchType->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'has_authors' => $data['has_authors'] ?? $researchType->has_authors,
                'is_active' => $data['is_active'],
                'updated_by' => $request->user()->id,
                'deleted_at' => null,
            ]);

            ResearchTypePointSetting::query()->updateOrCreate(
                ['research_type' => $researchType->slug],
                ['points_per_output' => $data['points'], 'updated_by' => $request->user()->id],
            );
        });

        return back()->with('success', 'Research involvement type updated.');
    }

    public function destroy(Request $request, ResearchType $researchType): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $researchType->update(['is_active' => false]);
        $researchType->delete();

        return back()->with('success', 'Research involvement type archived; its key and points remain reserved for historical records.');
    }
}
