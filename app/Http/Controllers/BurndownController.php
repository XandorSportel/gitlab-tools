<?php

namespace App\Http\Controllers;

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
        $metric = $requestedMetric === 'weight' ? 'weight' : 'count';

        try {
            $client = $this->client();
            $milestoneData = $client->milestone($this->project(), $milestone);
            $issues = $client->milestoneIssues($this->project(), $milestone);
            $chart = $calculator->calculate($milestoneData, $issues, $metric);
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
}
