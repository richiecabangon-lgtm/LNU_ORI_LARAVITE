<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResearchTypePointController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/ResearchTypePoints/Index', [
            'researchTypes' => ResearchType::query()->orderBy('sort_order')->orderBy('name')->with('pointSetting')->get()
                ->map(fn (ResearchType $type): array => [
                    'value' => $type->slug,
                    'label' => $type->name,
                    'points' => (float) ($type->pointSetting?->points_per_output ?? 0),
                    'active' => $type->is_active,
                ])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'points' => ['required', 'array', 'min:1'],
            'points.*' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $allowedTypes = ResearchType::query()->pluck('slug')->all();
        validator(['types' => array_keys($data['points'])], [
            'types.*' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
        ])->validate();

        foreach ($data['points'] as $researchType => $points) {
            ResearchTypePointSetting::query()->updateOrCreate(
                ['research_type' => $researchType],
                ['points_per_output' => $points, 'updated_by' => $request->user()->id],
            );
        }

        return back()->with('success', 'Research-type point values saved.');
    }
}
