<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchAgenda;
use App\Models\Sdg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SdgAgendaController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/ResearchMapping/Index', [
            'sdgs' => Sdg::query()->orderBy('number')->get(),
            'agendas' => ResearchAgenda::query()->orderBy('name')->get(),
        ]);
    }

    public function storeAgenda(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:research_agendas,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('research-agendas', 'public');
        }

        unset($data['image']);
        ResearchAgenda::query()->create($data);

        return back()->with('success', 'Research agenda added.');
    }

    public function updateAgenda(Request $request, ResearchAgenda $agenda): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('research_agendas', 'name')->ignore($agenda->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        unset($data['image']);
        $previousImage = $agenda->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('research-agendas', 'public');
        }

        $agenda->update($data);

        if ($previousImage && $agenda->image_path !== $previousImage) {
            Storage::disk('public')->delete($previousImage);
        }

        return back()->with('success', 'Research agenda updated.');
    }

    public function destroyAgenda(Request $request, ResearchAgenda $agenda): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($agenda->submissions()->exists()) {
            return back()->with('error', 'This agenda is used by submissions. Deactivate it instead.');
        }
        $imagePath = $agenda->image_path;
        $agenda->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'Research agenda deleted.');
    }

    public function updateSdg(Request $request, Sdg $sdg): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'number' => ['required', 'integer', 'between:1,17', Rule::unique('sdgs', 'number')->ignore($sdg->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        unset($data['image']);
        $previousImage = $sdg->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('sdgs', 'public');
        }

        $sdg->update($data);

        if ($previousImage && $sdg->image_path !== $previousImage) {
            Storage::disk('public')->delete($previousImage);
        }

        return back()->with('success', 'SDG details updated.');
    }
}
