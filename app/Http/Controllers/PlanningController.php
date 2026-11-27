<?php

namespace App\Http\Controllers;

use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = PlanningTask::query()
            ->with(['creator:id,name', 'assignees:id,name'])
            ->orderByDesc('id')
            ->simplePaginate(20);

        $assignableUsers = $request->user()->can('create planning tasks')
            ? $this->assignableUsers()
            : collect();

        return view('planning', compact('tasks', 'assignableUsers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTask($request);

        DB::transaction(function () use ($request, $validated): void {
            $task = new PlanningTask([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'gitlab_url' => $validated['gitlab_url'] ?? null,
            ]);
            $task->creator()->associate($request->user());
            $task->save();
            $task->assignees()->attach($validated['assignee_ids'] ?? []);
        });

        return redirect()->route('planning.index')->with('status', 'Task added.');
    }

    public function edit(PlanningTask $task): View
    {
        $assignableUsers = $this->assignableUsers();
        $selectedAssigneeIds = $task->assignees()->pluck('users.id')->all();

        return view('planning.edit', compact('task', 'assignableUsers', 'selectedAssigneeIds'));
    }

    public function update(Request $request, PlanningTask $task): RedirectResponse
    {
        $validated = $this->validateTask($request);

        DB::transaction(function () use ($task, $validated): void {
            $lockedTask = PlanningTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $lockedTask->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                ...Arr::only($validated, ['gitlab_url']),
            ]);
            $lockedTask->assignees()->sync($validated['assignee_ids'] ?? []);
        });

        return redirect()->route('planning.index')->with('status', 'Task updated.');
    }

    public function updateCompletion(Request $request, PlanningTask $task): RedirectResponse
    {
        $request->validate(['completed' => ['required', 'boolean']]);
        $completed = $request->boolean('completed');
        $taskQuery = PlanningTask::query()->whereKey($task->getKey());

        if ($completed) {
            $taskQuery->whereNull('completed_at')->update(['completed_at' => now()]);
        } else {
            $taskQuery->whereNotNull('completed_at')->update(['completed_at' => null]);
        }

        return redirect()->route('planning.index')->with('status', $completed ? 'Task completed.' : 'Task reopened.');
    }

    public function destroy(PlanningTask $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('planning.index')->with('status', 'Task deleted.');
    }

    /** @return array{title: string, description?: ?string, gitlab_url?: ?string, assignee_ids?: list<int|string>} */
    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'gitlab_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'assignee_ids' => ['sometimes', 'array', 'list'],
            'assignee_ids.*' => ['bail', 'required', 'integer', 'distinct', Rule::exists(User::class, 'id')],
        ]);
    }

    /** @return Collection<int, User> */
    private function assignableUsers(): Collection
    {
        return User::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'email']);
    }
}
