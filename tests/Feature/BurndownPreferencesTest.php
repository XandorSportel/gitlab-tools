<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BurndownPreferencesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chart_preference_hides_only_the_explanation(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(12, 0));
        config([
            'services.gitlab.url' => 'https://gitlab.example.com',
            'services.gitlab.project' => '123',
            'services.gitlab.token' => 'testing',
            'services.gitlab.workdays' => [1, 2, 3, 4, 5],
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://gitlab.example.com/api/v4/projects/123/milestones/10' => Http::response([
                'title' => 'Sprint fixture', 'start_date' => '2026-11-23', 'due_date' => '2026-11-27',
            ]),
            'https://gitlab.example.com/api/v4/projects/123/milestones/10/issues?*' => Http::response([
                ['iid' => 7, 'title' => 'Story', 'state' => 'opened', 'labels' => ['SP-5'], 'created_at' => '2026-11-23', 'web_url' => 'https://gitlab.example.com/issues/7'],
            ]),
            'https://gitlab.example.com/api/v4/projects/123/issues/7/resource_label_events?*' => Http::response([
                ['created_at' => '2026-11-23', 'action' => 'add', 'label' => ['name' => 'SP-5']],
            ]),
        ]);

        $user = User::factory()->create();
        $chart = route('burndown.show', ['milestone' => 10, 'metric' => 'story_points']);
        $settings = route('settings');

        $this->actingAs($user)->get($chart)
            ->assertOk()
            ->assertSeeHtml('<span>Ideal remaining today</span><strong>2.5</strong>');

        $this->from($settings)->patch(route('settings.burndown.update'), [
            'workdays' => [1, 2, 3, 4, 5], 'completed_labels' => 'Done', 'hide_chart_text' => '1',
        ])->assertRedirect($settings);

        $this->assertTrue($user->fresh()->burndown_hide_chart_text);
        $this->get($chart)
            ->assertOk()
            ->assertDontSeeText('Daily remaining work compared with the ideal trajectory.')
            ->assertDontSeeText('Working days')
            ->assertDontSeeText('Completion labels')
            ->assertSeeHtml('<span>Ideal remaining today</span><strong>2.5</strong>')
            ->assertSeeInOrder(['All milestones', 'Measure by', 'Initial scope', 'Burndown trajectory', 'id="burndown"', 'Daily changes'], false);

        $this->delete(route('settings.burndown.reset'))->assertRedirect($settings);
        $this->assertFalse($user->fresh()->burndown_hide_chart_text);
        $this->get($chart)->assertSeeText('Daily remaining work compared with the ideal trajectory.');
    }
}
