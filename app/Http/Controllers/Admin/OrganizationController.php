<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/Organizations/Index', [
            'colleges' => College::query()->with('departments')->orderBy('name')->get(),
        ]);
    }

    public function storeCollege(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:colleges,code'],
            'name' => ['required', 'string', 'max:255', 'unique:colleges,name'],
        ]);

        College::query()->create([...$data, 'is_active' => true]);

        return back()->with('success', 'College added.');
    }

    public function updateCollege(Request $request, College $college): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('colleges', 'code')->ignore($college->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('colleges', 'name')->ignore($college->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $college->update($data);

        return back()->with('success', 'College updated.');
    }

    public function destroyCollege(Request $request, College $college): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($college->departments()->exists() || $college->users()->exists()) {
            return back()->with('error', 'This college still has departments or assigned users. Deactivate it instead.');
        }
        $college->delete();

        return back()->with('success', 'College deleted.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $duplicateCode = Department::query()->where('college_id', $data['college_id'])->where('code', $data['code'])->exists();
        $duplicateName = Department::query()->where('college_id', $data['college_id'])->where('name', $data['name'])->exists();
        validator(['unique' => $duplicateCode || $duplicateName ? false : true], [
            'unique' => ['accepted'],
        ], ['unique.accepted' => 'This department code or name already exists in the selected college.'])->validate();

        Department::query()->create([...$data, 'is_active' => true]);

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $duplicate = Department::query()->where('college_id', $data['college_id'])
            ->where('id', '!=', $department->id)
            ->where(fn ($query) => $query->where('code', $data['code'])->orWhere('name', $data['name']))
            ->exists();
        validator(['unique' => ! $duplicate], [
            'unique' => ['accepted'],
        ], ['unique.accepted' => 'This department code or name already exists in the selected college.'])->validate();

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($department->users()->exists() || $department->submissions()->exists()) {
            return back()->with('error', 'This department still has assigned users or research records. Deactivate it instead.');
        }
        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
