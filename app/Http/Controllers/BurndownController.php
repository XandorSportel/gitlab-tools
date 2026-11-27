<?php

namespace App\Http\Controllers;

use App\Services\BurndownCalculator;
use App\Services\GitLabClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BurndownController extends Controller
{
    public function index(Request $request): View
    {
        $milestones = [];

        try {
            $milestones = $this->client()->milestones($this->project());
        } catch (Throwable $e) {
            return view('burndown', compact('milestones'))->with('error', $e->getMessage());
        }

        return view('burndown', compact('milestones'));
    }

    public function show(Request $request, int $milestone, BurndownCalculator $calculator): View|RedirectResponse
    {
        $requestedMetric = $request->string('metric')->toString();
        $metric = in_array($requestedMetric, ['count', 'weight', 'story_points'], true)
            ? $requestedMetric
            : 'count';

        try {
            $client = $this->client();
            $milestoneData = $client->milestone($this->project(), $milestone);
            $issues = $this->issuesWithHistory($client, $milestone);
            $burndownSettings = $request->user()->burndownSettings();
            $chart = $calculator->calculate(
                $milestoneData,
                $issues,
                $metric,
                $burndownSettings['completedLabels'],
                $burndownSettings['workdays'],
            );
        } catch (Throwable $e) {
            return redirect()->route('burndown.index')->withErrors(['chart' => $e->getMessage()]);
        }

        $healthCheck = $calculator->healthCheck($issues, $burndownSettings['completedLabels']);

        return view('chart', compact('milestoneData', 'issues', 'chart', 'metric', 'burndownSettings', 'healthCheck'));
    }

    public function retrospective(Request $request, BurndownCalculator $calculator): View
    {
        $validated = $request->validate([
            'milestones' => ['sometimes', 'array', 'max:3'],
            'milestones.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $selected = array_map(intval(...), $validated['milestones'] ?? []);
        $milestones = $rows = [];
        $error = null;
        $settings = $request->user()->burndownSettings();

        try {
            $client = $this->client();
            $today = CarbonImmutable::today()->toDateString();
            $milestones = array_filter($client->milestones($this->project()), fn (array $milestone): bool => isset($milestone['start_date'], $milestone['due_date'])
                && $milestone['start_date'] <= $milestone['due_date']
                && $milestone['due_date'] < $today
            );
            foreach ($milestones as $milestone) {
                if (! in_array((int) $milestone['id'], $selected, true)) {
                    continue;
                }

                try {
                    $issues = $this->issuesWithHistory($client, (int) $milestone['id']);
                    $rows[] = ['milestone' => $milestone, 'points' => $calculator->retrospective($milestone, $issues, $settings['completedLabels'])];
                } catch (Throwable $exception) {
                    report($exception);
                    $rows[] = ['milestone' => $milestone, 'points' => null];
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $error = 'Unable to load milestones from GitLab.';
        }

        return view('retrospective', compact('milestones', 'rows', 'selected', 'settings', 'error'));
    }

    private function issuesWithHistory(GitLabClient $client, int $milestone): array
    {
        $project = $this->project();
        $issues = array_filter(
            $client->milestoneIssues($project, $milestone),
            fn (array $issue): bool => ! $this->hasConfiguredLabel($issue, config('services.gitlab.ignored_issue_labels')),
        );

        return array_map(function (array $issue) use ($client, $project): array {
            $issue['label_events'] = $client->issueLabelEvents($project, $issue['iid'], $issue['updated_at'] ?? null);

            return $issue;
        }, array_values($issues));
    }

    private function client(): GitLabClient
    {
        $url = config('services.gitlab.url');
        $token = config('services.gitlab.token');
        if (! $token) {
            throw new \RuntimeException('GITLAB_TOKEN is not configured in the environment.');
        }

        return new GitLabClient($url, $token);
    }

    private function project(): string
    {
        $project = config('services.gitlab.project');
        if (! $project) {
            throw new \RuntimeException('GITLAB_PROJECT is not configured in the environment.');
        }

        return $project;
    }

    /**
     * @param  array<int, string>  $configuredLabels
     */
    private function hasConfiguredLabel(array $issue, array $configuredLabels): bool
    {
        $issueLabels = array_map(mb_strtolower(...), $issue['labels'] ?? []);

        foreach ($configuredLabels as $configuredLabel) {
            if (in_array(mb_strtolower($configuredLabel), $issueLabels, true)) {
                return true;
            }
        }

        return false;
    }
}
