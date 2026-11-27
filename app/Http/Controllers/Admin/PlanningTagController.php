<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanningTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanningTagController extends Controller
{
    public function index(): View
    {
        $tags = PlanningTag::query()->withCount('issues')->orderBy('name')->paginate(25);

        return view('admin.tags', compact('tags'));
    }

    public function store(Request $request): RedirectResponse
    {
        PlanningTag::create($this->validateTag($request));

        return redirect()->route('admin.tags.index')->with('status', 'Planning tag created.');
    }

    public function update(Request $request, PlanningTag $tag): RedirectResponse
    {
        $validated = $this->validateTag($request, $tag);
        DB::transaction(function () use ($tag, $validated): void {
            $tag->update($validated);
            if ($tag->wasChanged('name')) {
                $tag->issues()->touch();
            }
        });

        return redirect()->route('admin.tags.index')->with('status', 'Planning tag renamed.');
    }

    public function destroy(PlanningTag $tag): RedirectResponse
    {
        DB::transaction(function () use ($tag): void {
            $lockedTag = PlanningTag::query()->lockForUpdate()->findOrFail($tag->id);
            $lockedTag->issues()->touch();
            $lockedTag->delete();
        });

        return redirect()->route('admin.tags.index')->with('status', 'Planning tag deleted.');
    }

    /** @return array{name: string, name_key: string} */
    private function validateTag(Request $request, ?PlanningTag $tag = null): array
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:50', 'not_regex:/,/']]);
        $name = $validated['name'];
        $key = PlanningTag::nameKey($name);
        $duplicate = PlanningTag::query()->where('name_key', $key)->when($tag, fn ($query) => $query->whereKeyNot($tag->id))->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'A tag with this name already exists.']);
        }

        return ['name' => $name, 'name_key' => $key];
    }
}
