<?php

namespace App\Services;

use App\Models\PlanningIssue;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Str;
use RuntimeException;

class PlanningSprint
{
    public function milestones(): array
    {
        return $this->client()->milestones((string) config('services.gitlab.project'));
    }

    /** @return array<int, array{id: int, iid: int, title: string, url: ?string, updatedAt: int}> */
    public function issues(User $user): array
    {
        if ($user->planning_milestone_id === null) {
            return [];
        }

        $label = mb_strtolower($user->planningDoneLabel());
        $issues = [];

        foreach ($this->client()->milestoneIssues((string) config('services.gitlab.project'), $user->planning_milestone_id) as $issue) {
            if (($issue['state'] ?? null) !== 'opened' || in_array($label, array_map(mb_strtolower(...), $issue['labels'] ?? []), true)) {
                continue;
            }

            $id = (int) $issue['id'];
            $url = $issue['web_url'] ?? null;
            $issues[$id] = [
                'id' => $id,
                'iid' => (int) $issue['iid'],
                'title' => $issue['title'],
                'updatedAt' => strtotime($issue['updated_at'] ?? $issue['created_at'] ?? '') ?: 0,
                'url' => Str::isUrl($url, ['http', 'https']) ? $url : null,
            ];
        }

        return $issues;
    }

    /** @return array{id: int, iid: int, title: string, url: ?string, updatedAt: int} */
    public function findIssue(User $user, int $id): array
    {
        try {
            $issues = $this->issues($user);
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);
            abort(503, 'Unable to verify the GitLab issue. Try again later.');
        }

        abort_unless(isset($issues[$id]), 404);

        return $issues[$id];
    }

    public function instance(): string
    {
        return hash('sha256', rtrim((string) config('services.gitlab.url'), '/'));
    }

    public function localIssue(int $gitlabIssueId): PlanningIssue
    {
        return PlanningIssue::query()->firstOrCreate([
            'gitlab_instance' => $this->instance(),
            'gitlab_issue_id' => $gitlabIssueId,
        ]);
    }

    private function client(): GitLabClient
    {
        $project = config('services.gitlab.project');
        $token = config('services.gitlab.token');

        if (blank($project) || blank($token)) {
            throw new RuntimeException('The GitLab connection is not configured.');
        }

        return new GitLabClient((string) config('services.gitlab.url'), $token);
    }
}
