<?php

namespace App\Http\Controllers;

use App\Models\PlanningIssue;
use App\Models\PlanningTag;
use App\Models\PlanningTask;
use App\Services\PlanningSprint;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PlanningBoardController extends Controller
{
    public function index(Request $request, PlanningSprint $sprint): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'merge_status' => ['nullable', 'string', 'in:ready,action-needed'],
            'merge_request' => ['nullable', 'string', 'in:with-link,without-link'],
        ]);

        $search = $filters['q'] ?? '';
        $selectedTag = $filters['tag'] ?? '';
        $mergeStatus = $filters['merge_status'] ?? '';
        $mergeRequestFilter = $filters['merge_request'] ?? '';
        $hasFilters = $search !== '' || $selectedTag !== '' || $mergeStatus !== '' || $mergeRequestFilter !== '';
        $milestoneSelected = $request->user()->planning_milestone_id !== null;
        $error = null;

        try {
            $issues = $sprint->issues($request->user());
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);
            $issues = [];
            $error = 'Unable to load issues from GitLab. Check the selected milestone and GitLab connection.';
        }

        $localIssues = PlanningIssue::query()
            ->where('gitlab_instance', $sprint->instance())
            ->whereIn('gitlab_issue_id', array_keys($issues))
            ->with(['tags', 'tasks' => fn ($query) => $query->orderByDesc('id'), 'tasks.creator:id,name', 'tasks.assignees:id,name'])
            ->get()
            ->keyBy('gitlab_issue_id');

        $tags = $localIssues->flatMap(fn (PlanningIssue $issue): array => $issue->tags->pluck('name')->all())
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE);

        $availableTags = $milestoneSelected && ! $error && $request->user()->can('manage planning tags')
            ? PlanningTag::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $matchingIssues = collect($issues)->filter(function (array $issue) use ($localIssues, $search, $selectedTag, $mergeStatus, $mergeRequestFilter): bool {
            $localIssue = $localIssues->get($issue['id']);

            if ($selectedTag !== '' && ! $localIssue?->tags->contains(
                fn (PlanningTag $tag): bool => mb_strtolower($tag->name) === mb_strtolower($selectedTag),
            )) {
                return false;
            }

            if ($mergeStatus !== '' && $this->needsAction($localIssue) !== ($mergeStatus === 'action-needed')) {
                return false;
            }

            $hasMergeRequest = $localIssue?->merge_request_url !== null;
            if ($mergeRequestFilter !== '' && $hasMergeRequest !== ($mergeRequestFilter === 'with-link')) {
                return false;
            }

            return $search === '' || $this->matchesSearch($issue, $search)
                || ($localIssue?->tasks->contains(fn (PlanningTask $task): bool => mb_stripos($task->title, $search) !== false) ?? false);
        })->sortByDesc(function (array $issue) use ($localIssues): int {
            $localIssue = $localIssues->get($issue['id']);

            return max(
                $issue['updatedAt'],
                $localIssue?->updated_at?->getTimestamp() ?? 0,
                $localIssue?->tasks->max(fn (PlanningTask $task): int => $task->updated_at?->getTimestamp() ?? 0) ?? 0,
            );
        });

        $perPage = 10;
        $total = $matchingIssues->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $issuePage = min(Paginator::resolveCurrentPage(), $lastPage);
        $cards = new LengthAwarePaginator(
            $matchingIssues->forPage($issuePage, $perPage)->values(),
            $total,
            $perPage,
            $issuePage,
            ['path' => $request->url()],
        );
        $cards->withQueryString();
        $cards->through(function (array $issue) use ($localIssues, $request): array {
            $localIssue = $localIssues->get($issue['id']);
            $issueTags = $localIssue?->tags->pluck('name')->all() ?? [];
            $tasks = $localIssue?->tasks ?? collect();
            [$openTasks, $completedTasks] = $tasks->partition(fn (PlanningTask $task): bool => $task->completed_at === null);
            $pageName = 'completed_'.$issue['id'];
            $page = Paginator::resolveCurrentPage($pageName);
            $completedPage = new Paginator(
                $completedTasks->sortByDesc('completed_at')->forPage($page, 20)->values(),
                20,
                $page,
                ['path' => $request->url(), 'pageName' => $pageName],
            );
            $completedPage->hasMorePagesWhen($completedTasks->count() > $page * 20);
            $completedPage->withQueryString()->fragment('issue-'.$issue['iid']);

            return [
                'issue' => $issue,
                'tags' => $issueTags,
                'needsAction' => $this->needsAction($localIssue),
                'mergeRequestUrl' => $localIssue?->merge_request_url,
                'tagIds' => $localIssue?->tags->modelKeys() ?? [],
                'openTasks' => $openTasks,
                'completedTasks' => $completedPage,
                'completedCount' => $completedTasks->count(),
                'showCompleted' => $request->query->has($pageName),
            ];
        });

        return view('planning.board', compact('cards', 'tags', 'availableTags', 'search', 'selectedTag', 'mergeStatus', 'mergeRequestFilter', 'hasFilters', 'milestoneSelected', 'error'));
    }

    public function updateMergeRequest(Request $request, int $issue, PlanningSprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'merge_request_url' => ['present', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ]);

        $sprint->findIssue($request->user(), $issue);
        $localIssue = $sprint->localIssue($issue);
        $localIssue->merge_request_url = $validated['merge_request_url'];
        $localIssue->save();

        return redirect()->route('planning.index')->with('status', 'Merge request link updated.');
    }

    public function updateTags(Request $request, int $issue, PlanningSprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'tags' => ['sometimes', 'array', 'list', 'max:15'],
            'tags.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $tagIds = $validated['tags'] ?? [];

        $sprint->findIssue($request->user(), $issue);
        DB::transaction(function () use ($sprint, $issue, $tagIds): void {
            $existingTagIds = PlanningTag::query()->whereKey($tagIds)->sharedLock()->pluck('id');
            if ($existingTagIds->count() !== count($tagIds)) {
                throw ValidationException::withMessages(['tags' => 'Choose tags that still exist in the Planning tag list.']);
            }

            $localIssue = $sprint->localIssue($issue);
            $lockedIssue = PlanningIssue::query()->lockForUpdate()->findOrFail($localIssue->id);
            $changes = $lockedIssue->tags()->sync($existingTagIds);
            if ($changes['attached'] || $changes['detached']) {
                $lockedIssue->touch();
            }
        });

        return redirect()->route('planning.index')->with('status', 'Issue tags updated.');
    }

    private function needsAction(?PlanningIssue $issue): bool
    {
        return $issue !== null && ($issue->tags->isNotEmpty()
            || $issue->tasks->contains(fn (PlanningTask $task): bool => $task->completed_at === null));
    }

    /** @param array{id: int, iid: int, title: string, url: ?string, updatedAt: int} $issue */
    private function matchesSearch(array $issue, string $search): bool
    {
        if (preg_match('/^#?(\d+)$/D', $search, $matches)) {
            return $issue['iid'] === (int) $matches[1] || ($search[0] !== '#' && $issue['id'] === (int) $matches[1]);
        }

        return mb_stripos($issue['title'], $search) !== false;
    }
}
