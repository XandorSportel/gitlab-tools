<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BurndownCalculator;
use App\Services\GitLabClient;
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
            $issues = $client->milestoneIssues($this->project(), $milestone);
            $issues = array_values(array_filter(
                $issues,
                fn (array $issue): bool => ! $this->hasConfiguredLabel($issue, config('services.gitlab.ignored_issue_labels')),
            ));
            $issues = array_map(function (array $issue) use ($client): array {
                $issue['label_events'] = $client->issueLabelEvents($this->project(), $issue['iid']);

                return $issue;
            }, $issues);
            $chart = $calculator->calculate(
                $milestoneData,
                $issues,
                $metric,
                $request->user()->burndown_completed_labels ?? config('services.gitlab.completed_labels'),
                $request->user()->burndown_workdays ?? User::DEFAULT_BURNDOWN_WORKDAYS,
            );
        } catch (Throwable $e) {
            return redirect()->route('burndown.index')->withErrors(['chart' => $e->getMessage()]);
        }

        return view('chart', compact('milestoneData', 'issues', 'chart', 'metric'));
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
