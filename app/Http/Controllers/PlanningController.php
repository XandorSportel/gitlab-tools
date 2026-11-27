<?php

namespace App\Http\Controllers;

use App\Models\PlanningTask;
use App\Models\User;
use App\Services\PlanningSprint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(): View
    {
        $tasks = PlanningTask::query()
            ->whereNull('planning_issue_id')
            ->with(['assignees', 'creator']);

        $openTasks = (clone $tasks)->whereNull('completed_at')->latest('id')->get();
        $completedTasks = $tasks->whereNotNull('completed_at')
            ->latest('completed_at')->latest('id')->paginate(20)->fragment('completed-tasks');

        return view('planning.tasks', compact('openTasks', 'completedTasks'));
    }

    public function create(Request $request, PlanningSprint $sprint, ?int $issue = null): View
    {
        $gitLabIssue = $issue === null ? null : $sprint->findIssue($request->user(), $issue);
        $assignableUsers = $this->assignableUsers();

        return view('planning.create', compact('gitLabIssue', 'assignableUsers'));
    }

    public function store(Request $request, PlanningSprint $sprint, ?int $issue = null): RedirectResponse
    {
        $validated = $this->validateTask($request);

        if ($issue !== null) {
            $sprint->findIssue($request->user(), $issue);
        }

        DB::transaction(function () use ($request, $validated, $issue, $sprint): void {
            $task = new PlanningTask([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
            if ($issue !== null) {
                $task->issue()->associate($sprint->localIssue($issue));
            }
            $task->creator()->associate($request->user());
            $task->save();
            $task->assignees()->attach($validated['assignee_ids'] ?? []);
        });

        return redirect()->route($issue === null ? 'planning.tasks.index' : 'planning.index')->with('status', 'Task added.');
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
            ]);
            $changes = $lockedTask->assignees()->sync($validated['assignee_ids'] ?? []);
            if ($changes['attached'] || $changes['detached']) {
                $lockedTask->issue()->touch();
            }
        });

        return redirect()->route($task->planning_issue_id === null ? 'planning.tasks.index' : 'planning.index')->with('status', 'Task updated.');
    }

    public function updateCompletion(Request $request, PlanningTask $task): RedirectResponse
    {
        $request->validate(['completed' => ['required', 'boolean']]);
        $completed = $request->boolean('completed');
        DB::transaction(function () use ($task, $completed): void {
            $taskQuery = PlanningTask::query()->whereKey($task->getKey());
            $changed = $completed
                ? $taskQuery->whereNull('completed_at')->update(['completed_at' => now()])
                : $taskQuery->whereNotNull('completed_at')->update(['completed_at' => null]);

            if ($changed) {
                $task->issue()->touch();
            }
        });

        return redirect()->route($task->planning_issue_id === null ? 'planning.tasks.index' : 'planning.index')->with('status', $completed ? 'Task completed.' : 'Task reopened.');
    }

    public function destroy(PlanningTask $task): RedirectResponse
    {
        DB::transaction(fn () => $task->delete());

        return redirect()->route($task->planning_issue_id === null ? 'planning.tasks.index' : 'planning.index')->with('status', 'Task deleted.');
    }

    /** @return array{title: string, description?: ?string, assignee_ids?: list<int|string>} */
    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
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
