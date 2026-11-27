<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PlanningIssue;
use App\Models\PlanningTag;
use App\Models\PlanningTask;
use App\Models\User;
use Database\Seeders\PlanningPermissionSeeder;
use Database\Seeders\PlanningTagSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanningBoardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gitlab.url' => 'https://gitlab.example.com',
            'services.gitlab.project' => '123',
            'services.gitlab.token' => 'testing',
        ]);
        $this->withoutVite();
    }

    public function test_board_shows_only_open_issues_without_the_configured_done_label(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create([
            'planning_milestone_id' => 10,
            'planning_done_label' => 'Verified',
        ])->givePermissionTo('view planning');
        $issue = PlanningIssue::factory()->create(['gitlab_issue_id' => 70]);
        PlanningTask::factory()->for($issue, 'issue')->create(['title' => 'Review navigation']);
        PlanningTask::factory()->create(['title' => 'Old standalone task']);

        $this->actingAs($viewer)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSeeInOrder(['Action needed', 'Navigation story', 'Review navigation', 'Ready to merge', 'Login story'])
            ->assertSee('href="https://gitlab.example.com/team/project/-/issues/7"', false)
            ->assertDontSeeText('Verified story')
            ->assertDontSeeText('Closed story')
            ->assertDontSeeText('Old standalone task');

        Http::assertSentCount(1);
    }

    public function test_users_can_filter_cards_by_tag_issue_id_and_task_title(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $issue = PlanningIssue::factory()->create(['gitlab_issue_id' => 70]);
        $issue->tags()->attach(PlanningTag::factory()->create(['name' => 'Design Session', 'name_key' => PlanningTag::nameKey('Design Session')]));
        PlanningTask::factory()->for($issue, 'issue')->create(['title' => 'Review navigation']);

        $this->actingAs($viewer)
            ->get(route('planning.index', ['tag' => 'design session']))
            ->assertSeeText('Navigation story')
            ->assertSeeText('Review navigation')
            ->assertDontSeeText('Login story');

        $this->get(route('planning.index', ['q' => '#8']))
            ->assertSeeText('Login story')
            ->assertDontSeeText('Navigation story')
            ->assertDontSeeText('Default done story');

        $this->get(route('planning.index', ['q' => 'review navigation']))
            ->assertSeeText('Navigation story')
            ->assertDontSeeText('Login story');
    }

    public function test_users_can_filter_issues_by_merge_status(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $issue = PlanningIssue::factory()->create(['gitlab_issue_id' => 70]);
        $task = PlanningTask::factory()->for($issue, 'issue')->create();

        $this->actingAs($viewer)->get(route('planning.index', ['merge_status' => 'ready']))
            ->assertOk()
            ->assertSeeText('Login story')
            ->assertDontSeeText('Navigation story');

        $task->forceFill(['completed_at' => now()])->save();
        $this->get(route('planning.index', ['merge_status' => 'ready', 'q' => 'Navigation']))
            ->assertSeeText('Navigation story')
            ->assertDontSeeText('Login story');

        $tag = PlanningTag::factory()->create();
        $issue->tags()->attach($tag);

        $this->get(route('planning.index', ['merge_status' => 'action-needed', 'tag' => $tag->name]))
            ->assertSeeText('Navigation story')
            ->assertDontSeeText('Login story');

        $this->get(route('planning.index', ['merge_status' => 'ready', 'q' => 'Navigation']))
            ->assertSeeText('No issues match these filters.');
    }

    public function test_issues_paginate_in_groups_of_ten_after_search_and_tag_filters(): void
    {
        $this->freezeTime();
        $this->seed(PlanningPermissionSeeder::class);
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $tag = PlanningTag::factory()->create(['name' => 'Design Session', 'name_key' => PlanningTag::nameKey('Design Session')]);
        $issues = [];

        foreach (range(1, 23) as $id) {
            $issues[] = [
                'id' => $id,
                'iid' => $id,
                'state' => 'opened',
                'labels' => [],
                'title' => $id === 1 ? 'Different title' : sprintf('Matching issue %02d', $id),
            ];
            if ($id !== 2) {
                PlanningIssue::factory()->create(['gitlab_issue_id' => $id])->tags()->attach($tag);
            }
        }

        Http::preventStrayRequests();
        Http::fake(['https://gitlab.example.com/api/v4/projects/123/milestones/10/issues?*' => Http::response($issues)]);

        $filters = ['q' => 'Matching', 'tag' => 'Design Session', 'merge_status' => 'action-needed'];
        $this->actingAs($viewer)->get(route('planning.index', $filters))
            ->assertOk()
            ->assertSeeText('Matching issue 03')
            ->assertSeeText('Matching issue 12')
            ->assertDontSeeText('Matching issue 02')
            ->assertDontSeeText('Matching issue 13')
            ->assertDontSeeText('Different title')
            ->assertSee('aria-current="page">1</span>', false)
            ->assertSeeText('Showing 10 of 21')
            ->assertSee('q=Matching&amp;tag=Design%20Session&amp;merge_status=action-needed&amp;page=3', false)
            ->assertSee('q=Matching&amp;tag=Design%20Session&amp;merge_status=action-needed&amp;page=2', false);

        $this->get(route('planning.index', [...$filters, 'page' => 2]))
            ->assertOk()
            ->assertSeeText('Matching issue 13')
            ->assertDontSeeText('Matching issue 12')
            ->assertSee('aria-current="page">2</span>', false)
            ->assertSee('q=Matching&amp;tag=Design%20Session&amp;merge_status=action-needed&amp;page=1', false);

        $this->get(route('planning.index', [...$filters, 'page' => 3]))
            ->assertOk()
            ->assertSeeText('Matching issue 23')
            ->assertDontSeeText('Matching issue 22')
            ->assertSee('aria-current="page">3</span>', false)
            ->assertSeeText('Showing 1 of 21');
    }

    public function test_issue_order_uses_gitlab_updates_and_local_task_and_tag_changes(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(10, 0));
        $editor = User::factory()->create(['planning_milestone_id' => 10])
            ->givePermissionTo('view planning', 'manage planning tags', 'edit planning tasks', 'delete planning tasks');

        Http::preventStrayRequests();
        Http::fake(['https://gitlab.example.com/api/v4/projects/123/milestones/10/issues?*' => Http::response([
            ['id' => 70, 'iid' => 7, 'state' => 'opened', 'title' => 'Navigation story', 'updated_at' => '2026-11-28T09:00:00Z'],
            ['id' => 80, 'iid' => 8, 'state' => 'opened', 'title' => 'Login story', 'updated_at' => '2026-11-28T11:00:00Z'],
        ])]);

        $navigation = PlanningIssue::factory()->create(['gitlab_issue_id' => 70]);
        $login = PlanningIssue::factory()->create(['gitlab_issue_id' => 80]);
        $task = PlanningTask::factory()->for($login, 'issue')->create();
        $tag = PlanningTag::factory()->create();

        $this->actingAs($editor)->get(route('planning.index'))
            ->assertSeeInOrder(['Login story', 'Navigation story']);

        $this->travel(2)->hours();
        $this->patch(route('planning.tags.update', 70), ['tags' => [$tag->id]])->assertRedirect();
        $this->get(route('planning.index'))->assertSeeInOrder(['Navigation story', 'Login story']);

        $this->travel(1)->hour();
        $this->patch(route('planning.completion.update', $task), ['completed' => '1'])->assertRedirect();
        $this->get(route('planning.index'))->assertSeeInOrder(['Login story', 'Navigation story']);

        $this->travel(1)->hour();
        $this->patch(route('planning.tags.update', 70), [])->assertRedirect();
        $this->get(route('planning.index'))->assertSeeInOrder(['Navigation story', 'Login story']);

        $this->travel(1)->hour();
        $this->delete(route('planning.destroy', $task))->assertRedirect();
        $this->get(route('planning.index'))->assertSeeInOrder(['Login story', 'Navigation story']);
        $this->assertSame('2026-11-28 15:00:00', $login->fresh()->updated_at->toDateTimeString());
        $this->assertSame('2026-11-28 14:00:00', $navigation->fresh()->updated_at->toDateTimeString());
    }

    public function test_tag_editors_can_select_and_clear_existing_tags(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $editor = User::factory()->create(['planning_milestone_id' => 10])
            ->givePermissionTo('view planning', 'manage planning tags');

        $this->seed(PlanningTagSeeder::class);
        $tags = PlanningTag::query()->orderBy('id')->get();

        $this->actingAs($editor)
            ->get(route('planning.index'))
            ->assertSee('name="tags[]"', false)
            ->assertSeeText('Design Session');

        $this->patch(route('planning.tags.update', 70), ['tags' => $tags->modelKeys()])
            ->assertRedirect(route('planning.index'));

        $issue = PlanningIssue::query()->sole();
        $this->assertSame(['Design Session', 'Requires testing'], $issue->tags()->pluck('planning_tags.name')->all());
        $this->get(route('planning.index', ['tag' => 'Requires testing']))
            ->assertSeeText('Navigation story')
            ->assertSee('>Action needed</p>', false)
            ->assertDontSee('>Ready to merge</p>', false)
            ->assertDontSeeText('Login story');

        PlanningTask::factory()->for($issue, 'issue')->create(['completed_at' => now()]);
        $this->get(route('planning.index', ['tag' => 'Requires testing']))
            ->assertSee('>Action needed</p>', false)
            ->assertDontSee('>Ready to merge</p>', false);

        $this->patch(route('planning.tags.update', 70), [])
            ->assertRedirect(route('planning.index'));
        $this->assertSame([], $issue->tags()->pluck('planning_tags.name')->all());
        $this->get(route('planning.index', ['q' => '#7']))
            ->assertSee('>Ready to merge</p>', false)
            ->assertDontSee('>Action needed</p>', false);

        Http::assertNotSent(fn ($request): bool => $request->method() !== 'GET');
    }

    public function test_issue_tag_updates_reject_names_and_unknown_tag_ids(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $editor = User::factory()->create(['planning_milestone_id' => 10])
            ->givePermissionTo('view planning', 'manage planning tags');
        $this->actingAs($editor)->from(route('planning.index'));

        $this->patch(route('planning.tags.update', 70), ['tags' => 'Ad hoc tag'])->assertSessionHasErrors('tags');
        $this->patch(route('planning.tags.update', 70), ['tags' => [999]])->assertSessionHasErrors('tags');

        $this->assertDatabaseEmpty('planning_tags');
        $this->assertDatabaseEmpty('planning_issues');
    }

    public function test_task_editors_can_save_and_remove_a_merge_request_link(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $editor = User::factory()->create(['planning_milestone_id' => 10])
            ->givePermissionTo('view planning', 'edit planning tasks');
        $update = route('planning.merge-request.update', 70);
        $board = route('planning.index');
        $url = 'https://gitlab.example.com/team/project/-/merge_requests/42';

        $this->actingAs($viewer)->patch($update, ['merge_request_url' => $url])->assertForbidden();

        $this->actingAs($editor)->patch(route('planning.merge-request.update', 73), ['merge_request_url' => $url])
            ->assertNotFound();
        $this->from($board)->patch($update, ['merge_request_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('merge_request_url');
        $this->assertDatabaseEmpty('planning_issues');

        $this->patch($update, ['merge_request_url' => $url])->assertRedirect($board);
        $issue = PlanningIssue::query()->sole();
        $this->assertSame(70, $issue->gitlab_issue_id);
        $this->assertSame($url, $issue->merge_request_url);

        $this->actingAs($viewer)->get($board)
            ->assertSeeHtml('href="'.$url.'"')
            ->assertSeeText('Open merge request')
            ->assertDontSeeText('Edit merge request link');

        $this->actingAs($editor)->patch($update, ['merge_request_url' => ''])->assertRedirect($board);
        $this->assertNull($issue->fresh()->merge_request_url);
        $this->get($board)->assertDontSeeText('Open merge request')->assertSeeText('Add merge request link');
    }

    public function test_writes_require_permissions_and_a_current_open_issue(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');

        $this->actingAs($viewer)
            ->patch(route('planning.tags.update', 70), ['tags' => 'Design Session'])
            ->assertForbidden();

        $planner = User::factory()->create(['planning_milestone_id' => 10])
            ->givePermissionTo('view planning', 'manage planning tags', 'create planning tasks');

        $this->actingAs($planner)
            ->get(route('planning.create', 70))
            ->assertOk()
            ->assertSeeText('Navigation story')
            ->assertSee(route('planning.store', 70));

        $this->post(route('planning.store', 999), ['title' => 'Outside sprint'])->assertNotFound();
        $this->patch(route('planning.tags.update', 73), ['tags' => []])->assertNotFound();
        $this->assertDatabaseEmpty('planning_issues');
        $this->assertDatabaseEmpty('planning_tasks');
    }

    public function test_planning_settings_select_a_milestone_and_preserve_burndown_preferences(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        $this->fakeSprint();
        $viewer = User::factory()->create([
            'burndown_workdays' => [1, 2, 3],
            'burndown_completed_labels' => ['In MR'],
        ])->givePermissionTo('view planning');

        $this->actingAs($viewer)
            ->get(route('planning.index'))
            ->assertSeeText('Choose a current sprint');

        $this->get(route('settings'))
            ->assertSeeText('Burndown preferences')
            ->assertSeeText('Planning preferences')
            ->assertSeeText('Sprint A');

        $this->patch(route('settings.planning.update'), [
            'planning_milestone_id' => 10,
            'planning_done_label' => 'Verified',
        ])->assertRedirect(route('settings').'#planning-settings');

        $this->get(route('settings'))
            ->assertSeeInOrder(['Planning preferences</h3>', 'Planning preferences updated.', 'Save planning preferences'], false);

        $saved = $viewer->fresh();
        $this->assertSame(10, $saved->planning_milestone_id);
        $this->assertSame('Verified', $saved->planning_done_label);
        $this->assertSame([1, 2, 3], $saved->burndown_workdays);
        $this->assertSame(['In MR'], $saved->burndown_completed_labels);

        $this->from(route('settings'))->patch(route('settings.planning.update'), [
            'planning_milestone_id' => 999,
            'planning_done_label' => 'Other',
        ])->assertSessionHasErrors('planning_milestone_id');

        $this->assertSame(10, $viewer->fresh()->planning_milestone_id);
    }

    private function fakeSprint(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gitlab.example.com/api/v4/projects/123/milestones/10/issues?*' => Http::response([
                ['id' => 70, 'iid' => 7, 'state' => 'opened', 'labels' => [], 'title' => 'Navigation story', 'web_url' => 'https://gitlab.example.com/team/project/-/issues/7'],
                ['id' => 80, 'iid' => 8, 'state' => 'opened', 'labels' => [], 'title' => 'Login story'],
                ['id' => 71, 'iid' => 71, 'state' => 'opened', 'labels' => ['verified'], 'title' => 'Verified story'],
                ['id' => 72, 'iid' => 72, 'state' => 'closed', 'labels' => [], 'title' => 'Closed story'],
                ['id' => 73, 'iid' => 73, 'state' => 'opened', 'labels' => ['Done'], 'title' => 'Default done story'],
            ]),
            'https://gitlab.example.com/api/v4/projects/123/milestones?*' => Http::response([
                ['id' => 10, 'title' => 'Sprint A'],
            ]),
        ]);
    }
}
