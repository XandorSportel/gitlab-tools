<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    public function project(string $project): array
    {
        return $this->get('/projects/'.rawurlencode($project));
    }

    public function milestones(string $project): array
    {
        $milestones = $this->all('/projects/'.rawurlencode($project).'/milestones');

        usort($milestones, fn (array $a, array $b) => strcmp(
            $b['due_date'] ?? '0000-00-00',
            $a['due_date'] ?? '0000-00-00',
        ));

        return $milestones;
    }

    public function milestone(string $project, int $milestoneId): array
    {
        return $this->get('/projects/'.rawurlencode($project).'/milestones/'.$milestoneId);
    }

    public function milestoneIssues(string $project, int $milestoneId): array
    {
        return $this->all('/projects/'.rawurlencode($project).'/milestones/'.$milestoneId.'/issues');
    }

    public function issueLabelEvents(string $project, int $issueIid, ?string $updatedAt = null): array
    {
        $path = '/projects/'.rawurlencode($project).'/issues/'.$issueIid.'/resource_label_events';
        $key = 'gitlab:label-events:'.hash('sha256', implode("\n", [$this->baseUrl, $this->token, $path, $updatedAt]));

        $cacheSeconds = filled($updatedAt) ? 900 : 120;

        return Cache::remember($key, $cacheSeconds, function () use ($path): array {
            $events = $this->all($path);

            usort($events, fn (array $first, array $second) => strcmp(
                $first['created_at'],
                $second['created_at'],
            ));

            return array_map(fn (array $event): array => [
                'created_at' => $event['created_at'],
                'action' => $event['action'],
                'label' => $event['label'] === null ? null : ['name' => $event['label']['name']],
            ], $events);
        });
    }

    private function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($path, $query);

        if ($response->failed()) {
            $message = $response->json('message') ?: 'GitLab returned HTTP '.$response->status().'.';
            throw new RuntimeException(is_array($message) ? json_encode($message) : $message);
        }

        return $response->json();
    }

    private function all(string $path, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request()->get($path, $query + ['per_page' => 100, 'page' => $page]);
            if ($response->failed()) {
                throw new RuntimeException($response->json('message') ?: 'GitLab returned HTTP '.$response->status().'.');
            }
            $items = array_merge($items, $response->json());
            $next = $response->header('X-Next-Page');
            $page = $next !== '' ? (int) $next : 0;
        } while ($page > 0);

        return $items;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/api/v4')
            ->withHeaders(['PRIVATE-TOKEN' => $this->token])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 200);
    }
}
