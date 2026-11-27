<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanningTaskTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanningTaskTemplateController extends Controller
{
    public function index(): View
    {
        $templates = PlanningTaskTemplate::query()->orderBy('title')->paginate(25);

        return view('admin.task-templates', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        PlanningTaskTemplate::create($this->validateTemplate($request));

        return redirect()->route('admin.task-templates.index')->with('status', 'Standard task added.');
    }

    public function update(Request $request, PlanningTaskTemplate $template): RedirectResponse
    {
        $template->update($this->validateTemplate($request, $template));

        return redirect()->route('admin.task-templates.index')->with('status', 'Standard task updated.');
    }

    public function destroy(PlanningTaskTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.task-templates.index')->with('status', 'Standard task deleted.');
    }

    /** @return array{title: string} */
    private function validateTemplate(Request $request, ?PlanningTaskTemplate $template = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique(PlanningTaskTemplate::class)->ignore($template?->id)],
        ]);
    }
}
