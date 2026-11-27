<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PlanningIssue;
use App\Models\PlanningTask;
use App\Models\User;
use Database\Factories\PlanningTaskFactory;
use Database\Seeders\PlanningPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanningControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('planning.index'))->assertRedirect(route('login'));
        $this->post(route('planning.store', 42))->assertRedirect(route('login'));
    }

    public function test_accounts_without_planning_access_cannot_view_planning(): void
    {
        $this->prepareIssue();

        $this->actingAs(User::factory()->create())
            ->get(route('planning.index'))
            ->assertForbidden();
    }

    public function test_viewers_can_see_tasks_but_cannot_create_them(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $this->taskFactory()->create(['title' => '<Review sprint>', 'description' => '<Share notes>']);

        $this->actingAs($viewer)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSee('<Review sprint>')
            ->assertSee('<Share notes>')
            ->assertDontSee('<Review sprint>', false)
            ->assertDontSee('<Share notes>', false)
            ->assertDontSeeText('Add task');

        $this->post(route('planning.store', 42), ['title' => 'Write attempt'])->assertForbidden();
        $this->assertDatabaseMissing('planning_tasks', ['title' => 'Write attempt']);
    }

    public function test_planners_can_create_a_task_and_assign_multiple_users(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $planner = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'create planning tasks');
        [$firstAssignee, $secondAssignee] = User::factory()->count(2)->create();

        $this->actingAs($planner)
            ->post(route('planning.store', 42), [
                'title' => 'Prepare sprint planning',
                'description' => 'Bring the backlog.',
                'gitlab_url' => 'https://gitlab.example.com/team/project/-/issues/42',
                'created_by' => $firstAssignee->id,
                'assignee_ids' => [$firstAssignee->id, $secondAssignee->id],
            ])
            ->assertRedirect(route('planning.index'));

        $task = PlanningTask::query()->sole();
        $this->assertSame($planner->id, $task->created_by);
        $this->assertSame($this->issue()->id, $task->planning_issue_id);
        $this->assertSame('https://gitlab.example.com/team/project/-/issues/42', $task->gitlab_url);
        $this->assertEqualsCanonicalizing(
            [$firstAssignee->id, $secondAssignee->id],
            $task->assignees()->pluck('users.id')->all(),
        );

        $this->get(route('planning.index'))
            ->assertOk()
            ->assertSee('Prepare sprint planning')
            ->assertSee('Bring the backlog.')
            ->assertSee('href="https://gitlab.example.com/team/project/-/issues/42"', false)
            ->assertSeeText('Open in GitLab')
            ->assertDontSeeText('https://gitlab.example.com/team/project/-/issues/42')
            ->assertSee($firstAssignee->name)
            ->assertSee($secondAssignee->name);
    }

    public function test_planners_can_create_an_unassigned_task(): void
    {
        $this->prepareIssue();
        $planner = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'create planning tasks');

        $this->actingAs($planner)
            ->post(route('planning.store', 42), ['title' => 'Review the backlog'])
            ->assertRedirect(route('planning.index'));

        $this->assertDatabaseHas('planning_tasks', ['title' => 'Review the backlog', 'gitlab_url' => null]);
        $this->assertDatabaseEmpty('planning_task_user');
    }

    public function test_invalid_tasks_are_rejected_without_being_saved(): void
    {
        $this->prepareIssue();
        $planner = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'create planning tasks');

        $this->actingAs($planner)
            ->from(route('planning.index'))
            ->post(route('planning.store', 42), [
                'assignee_ids' => [$planner->id + 1],
                'gitlab_url' => 'javascript:alert(1)',
            ])
            ->assertRedirect(route('planning.index'))
            ->assertSessionHasErrors(['title', 'assignee_ids.0', 'gitlab_url']);

        $this->assertDatabaseEmpty('planning_tasks');
    }

    public function test_duplicate_assignments_are_rejected(): void
    {
        $this->prepareIssue();
        $planner = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'create planning tasks');

        $this->actingAs($planner)
            ->from(route('planning.index'))
            ->post(route('planning.store', 42), [
                'title' => 'Review the backlog',
                'assignee_ids' => [$planner->id, (string) $planner->id],
            ])
            ->assertSessionHasErrors('assignee_ids.1');

        $this->assertDatabaseEmpty('planning_tasks');
    }

    public function test_viewers_cannot_edit_or_delete_tasks(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');
        $task = $this->taskFactory()->create(['title' => 'Original title']);

        $this->actingAs($viewer)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertDontSee(route('planning.edit', $task))
            ->assertDontSee('data-open-modal', false);

        $this->get(route('planning.edit', $task))->assertForbidden();
        $this->patch(route('planning.update', $task), ['title' => 'Changed title'])->assertForbidden();
        $this->delete(route('planning.destroy', $task))->assertForbidden();
        $this->patch(route('planning.completion.update', $task), ['completed' => '1'])->assertForbidden();
        $this->assertNull($task->fresh()->completed_at);
        $this->assertDatabaseHas('planning_tasks', ['id' => $task->id, 'title' => 'Original title']);
    }

    public function test_editors_can_change_task_details_and_replace_assignees(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $editor = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'edit planning tasks');
        [$originalAssignee, $newAssignee] = User::factory()->count(2)->create();
        $task = $this->taskFactory()->create([
            'title' => 'Original title',
            'gitlab_url' => 'https://gitlab.com/team/project/-/issues/1',
        ]);
        $task->assignees()->attach($originalAssignee);

        $this->actingAs($editor)
            ->get(route('planning.index'))
            ->assertSee(route('planning.edit', $task))
            ->assertDontSee('data-open-modal', false);

        $this->get(route('planning.edit', $task))
            ->assertOk()
            ->assertSee('value="Original title"', false)
            ->assertSee('value="https://gitlab.com/team/project/-/issues/1"', false)
            ->assertViewHas('selectedAssigneeIds', [$originalAssignee->id]);

        $this->patch(route('planning.update', $task), [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'gitlab_url' => 'https://gitlab.com/team/project/-/issues/2',
            'assignee_ids' => [$newAssignee->id],
        ])->assertRedirect(route('planning.index'));

        $updatedTask = $task->fresh();
        $this->assertSame('Updated title', $updatedTask->title);
        $this->assertSame('Updated description', $updatedTask->description);
        $this->assertSame('https://gitlab.com/team/project/-/issues/2', $updatedTask->gitlab_url);
        $this->assertSame($task->created_by, $updatedTask->created_by);
        $this->assertSame([$newAssignee->id], $updatedTask->assignees()->pluck('users.id')->all());

        $this->patch(route('planning.update', $task), ['title' => 'Updated title', 'gitlab_url' => ''])
            ->assertRedirect(route('planning.index'));
        $this->assertDatabaseEmpty('planning_task_user');
        $this->assertNull($task->fresh()->gitlab_url);
    }

    public function test_invalid_updates_keep_the_task_and_existing_assignee(): void
    {
        $this->prepareIssue();
        $editor = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'edit planning tasks');
        $task = $this->taskFactory()->create(['title' => 'Original title']);
        $task->assignees()->attach($editor);

        $this->actingAs($editor)
            ->from(route('planning.edit', $task))
            ->patch(route('planning.update', $task), [
                'title' => 'Changed title',
                'assignee_ids' => [0],
            ])
            ->assertRedirect(route('planning.edit', $task))
            ->assertSessionHasErrors('assignee_ids.0');

        $this->assertSame('Original title', $task->fresh()->title);
        $this->assertSame([$editor->id], $task->assignees()->pluck('users.id')->all());
    }

    public function test_users_with_delete_permission_can_remove_a_task_and_its_assignments(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $user = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'delete planning tasks');
        $task = $this->taskFactory()->create();
        $task->assignees()->attach($user);

        $this->actingAs($user)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSee('data-open-modal="delete-task-'.$task->id.'"', false)
            ->assertSee('id="delete-task-'.$task->id.'"', false)
            ->assertSee('action="'.route('planning.destroy', $task).'"', false)
            ->assertSee('method="dialog"', false)
            ->assertDontSee(route('planning.edit', $task));

        $this->delete(route('planning.destroy', $task))
            ->assertRedirect(route('planning.index'));

        $this->assertDatabaseMissing('planning_tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('planning_task_user', ['planning_task_id' => $task->id]);
    }

    public function test_editors_can_complete_and_reopen_tasks_with_a_completion_date(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $editor = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning', 'edit planning tasks');
        $task = $this->taskFactory()->create();
        $this->travelTo(now()->setDate(2026, 11, 27)->setTime(12, 0));

        $this->actingAs($editor)
            ->get(route('planning.index'))
            ->assertSee('action="'.route('planning.completion.update', $task).'"', false)
            ->assertDontSeeText('Completed on');

        $this->patch(route('planning.completion.update', $task), ['completed' => '1'])
            ->assertRedirect(route('planning.index'));
        $this->assertSame('2026-11-27 12:00:00', $task->fresh()->completed_at->toDateTimeString());

        $this->get(route('planning.index'))
            ->assertSee('datetime="2026-11-27"', false)
            ->assertSeeText('27 Nov 2026')
            ->assertSee('>Ready to merge</p>', false);

        $this->travel(1)->day();
        $this->patch(route('planning.completion.update', $task), ['completed' => '1'])
            ->assertRedirect(route('planning.index'));
        $this->assertSame('2026-11-27 12:00:00', $task->fresh()->completed_at->toDateTimeString());

        $this->patch(route('planning.completion.update', $task), ['completed' => '0'])
            ->assertRedirect(route('planning.index'));
        $this->assertNull($task->fresh()->completed_at);
        $this->get(route('planning.index'))->assertDontSeeText('Completed on');
    }

    public function test_completed_tasks_are_collapsed_initially_and_paginate_independently(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $viewer = User::factory()->create(['planning_milestone_id' => 10])->givePermissionTo('view planning');

        $this->taskFactory()->recycle($viewer)->create(['title' => 'Oldest open task']);
        $this->taskFactory()->recycle($viewer)->count(20)->create(['title' => 'Newer open task']);
        $this->taskFactory()->recycle($viewer)->count(20)->create([
            'title' => 'Recently completed task',
            'completed_at' => '2026-11-27 12:00:00',
        ]);
        $this->taskFactory()->recycle($viewer)->create([
            'title' => 'Earliest completed task',
            'completed_at' => '2026-11-26 12:00:00',
        ]);

        $this->actingAs($viewer)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertSeeInOrder(['Sprint issues', 'Newer open task', 'Oldest open task', 'Completed tasks', 'Recently completed task'])
            ->assertDontSeeText('Earliest completed task')
            ->assertSee('completed_42=2#issue-7', false);

        $this->get(route('planning.index', ['completed_42' => 2, 'q' => 'Sprint fixture']))
            ->assertOk()
            ->assertSeeText('Newer open task')
            ->assertSeeText('Earliest completed task')
            ->assertDontSeeText('Recently completed task')
            ->assertSee('q=Sprint%20fixture&amp;completed_42=1#issue-7', false);
    }

    public function test_standalone_tasks_are_grouped_by_completion_without_loading_gitlab_issues(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $viewer = User::factory()->create()->givePermissionTo('view planning');
        PlanningTask::factory()->recycle($viewer)->create(['title' => 'Open standalone task']);
        PlanningTask::factory()->recycle($viewer)->count(20)->create([
            'title' => 'Recently completed standalone task',
            'completed_at' => '2026-11-27 12:00:00',
        ]);
        PlanningTask::factory()->recycle($viewer)->create([
            'title' => 'Earliest completed standalone task',
            'completed_at' => '2026-11-26 12:00:00',
        ]);
        $this->taskFactory()->recycle($viewer)->create(['title' => 'Task linked to an issue']);

        $this->actingAs(User::factory()->create())->get(route('planning.tasks.index'))->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('planning.tasks.index'))
            ->assertOk()
            ->assertSeeInOrder(['Open tasks', 'Open standalone task', 'Completed tasks (21)', 'Recently completed standalone task'])
            ->assertDontSeeText('Earliest completed standalone task')
            ->assertDontSeeText('Task linked to an issue')
            ->assertDontSeeText('Add task')
            ->assertSee('page=2#completed-tasks', false);

        $this->post(route('planning.tasks.store'), ['title' => 'Write attempt'])->assertForbidden();

        $this->get(route('planning.tasks.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeInOrder(['Open standalone task', 'Completed tasks (21)', 'Earliest completed standalone task'])
            ->assertDontSeeText('Recently completed standalone task');

        Http::assertNothingSent();
    }

    public function test_planners_can_manage_a_standalone_task_and_return_to_the_task_list(): void
    {
        $this->withoutVite();
        $this->prepareIssue();
        $planner = User::factory()->create()->givePermissionTo(
            'view planning', 'create planning tasks', 'edit planning tasks', 'delete planning tasks',
        );
        $assignee = User::factory()->create();
        $taskList = route('planning.tasks.index');

        $this->actingAs($planner)->get(route('planning.tasks.create'))
            ->assertOk()
            ->assertSee('action="'.route('planning.tasks.store').'"', false);

        $this->post(route('planning.tasks.store'), ['title' => 'Plan a demo'])
            ->assertRedirect($taskList);

        $task = PlanningTask::query()->sole();
        $this->assertNull($task->planning_issue_id);

        $this->get(route('planning.edit', $task))->assertOk()->assertSee('Standalone Tasks');
        $this->patch(route('planning.update', $task), [
            'title' => 'Prepare a demo',
            'assignee_ids' => [$assignee->id],
        ])->assertRedirect($taskList);

        $this->patch(route('planning.completion.update', $task), ['completed' => '1'])
            ->assertRedirect($taskList);
        $this->get($taskList)->assertSeeInOrder(['Completed tasks (1)', 'Prepare a demo', 'Completed on', $assignee->name]);

        $this->patch(route('planning.completion.update', $task), ['completed' => '0'])
            ->assertRedirect($taskList);
        $this->get($taskList)->assertSeeInOrder(['Open tasks', 'Prepare a demo', 'Completed tasks (0)']);

        $this->delete(route('planning.destroy', $task))->assertRedirect($taskList);
        $this->assertModelMissing($task);
        Http::assertNothingSent();
    }

    private function prepareIssue(): void
    {
        $this->seed(PlanningPermissionSeeder::class);
        config([
            'services.gitlab.url' => 'https://gitlab.example.com',
            'services.gitlab.project' => '123',
            'services.gitlab.token' => 'testing',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://gitlab.example.com/api/v4/projects/123/milestones/10/issues?*' => Http::response([
                ['id' => 42, 'iid' => 7, 'state' => 'opened', 'title' => 'Sprint fixture', 'labels' => []],
            ]),
        ]);
    }

    private function issue(): PlanningIssue
    {
        return PlanningIssue::query()->firstOrCreate([
            'gitlab_instance' => hash('sha256', 'https://gitlab.example.com'),
            'gitlab_issue_id' => 42,
        ]);
    }

    private function taskFactory(): PlanningTaskFactory
    {
        return PlanningTask::factory()->for($this->issue(), 'issue');
    }
}
