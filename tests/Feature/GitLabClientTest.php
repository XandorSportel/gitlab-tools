<?php

namespace Tests\Feature;

use App\Services\GitLabClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitLabClientTest extends TestCase
{
    public function test_label_events_are_reused_until_the_issue_changes_or_the_cache_expires(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://gitlab.example/api/v4/projects/1/issues/2/resource_label_events?*' => Http::response([])]);
        $client = new GitLabClient('https://gitlab.example', 'test-token');

        $this->assertSame([], $client->issueLabelEvents('1', 2, '2026-11-28T10:00:00Z'));
        $this->assertSame([], $client->issueLabelEvents('1', 2, '2026-11-28T10:00:00Z'));
        Http::assertSentCount(1);

        $client->issueLabelEvents('1', 2, '2026-11-28T10:01:00Z');
        Http::assertSentCount(2);

        $this->travel(121)->seconds();
        $client->issueLabelEvents('1', 2, '2026-11-28T10:01:00Z');
        $this->travelBack();
        Http::assertSentCount(3);
    }
}
