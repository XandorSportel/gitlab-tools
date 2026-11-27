<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PlanningIssue;
use App\Models\PlanningTag;
use App\Models\PlanningTaskTemplate;
use App\Models\User;
use App\Services\AdminAccess;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBackofficeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_seeder_creates_one_privileged_user_and_preserves_their_password_on_rerun(): void
    {
        $admin = $this->seedAdmin();
        $this->assertTrue(Hash::check('InitialAdminPassword123', $admin->password));

        config(['admin.password' => null]);
        $this->seed(AdminUserSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($admin->password, $admin->fresh()->password);
        $this->assertTrue($admin->hasRole(AdminAccess::Administrator));
        $this->assertTrue($admin->can('manage planning tags'));

        $this->withoutVite()->actingAs($admin);
        foreach (array_keys(AdminAccess::Pages) as $page) {
            $this->get(route('admin.'.$page.'.index'))->assertOk();
        }
    }

    #[TestWith(['users', 'admin users'])]
    #[TestWith(['roles', 'admin roles'])]
    #[TestWith(['permissions', 'admin permissions'])]
    #[TestWith(['tags', 'admin planning tags'])]
    #[TestWith(['task-templates', 'admin planning task templates'])]
    public function test_each_admin_page_requires_its_own_permission(string $page, string $permission): void
    {
        $this->withoutVite();
        $viewedRoute = route('admin.'.$page.'.index');
        $this->get($viewedRoute)->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get($viewedRoute)->assertForbidden();
        $this->get(route('profile'))->assertDontSee('aria-label="Admin"', false);

        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $this->get($viewedRoute)->assertOk()->assertSee('aria-label="Admin"', false);

        foreach (array_diff(array_keys(AdminAccess::Pages), [$page]) as $otherPage) {
            $this->get($viewedRoute)->assertDontSee(route('admin.'.$otherPage.'.index'));
            $this->get(route('admin.'.$otherPage.'.index'))->assertForbidden();
        }
    }

    public function test_admin_can_manage_users_and_assign_roles_and_direct_permissions(): void
    {
        $this->withoutVite();
        $admin = $this->seedAdmin();
        $role = Role::create(['name' => 'Planning reader', 'guard_name' => 'web'])
            ->givePermissionTo('view planning');
        $direct = Permission::findByName('edit planning tasks', 'web');

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->post(route('admin.users.store'), [
            'name' => 'New Planner',
            'email' => 'new-planner@example.test',
            'password' => 'PlannerPassword123',
            'password_confirmation' => 'PlannerPassword123',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new-planner@example.test')->sole();
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSeeText('Direct permissions');

        $this->patch(route('admin.users.access', $user), [
            'roles' => [$role->id],
            'permissions' => [$direct->id],
        ])->assertRedirect(route('admin.users.edit', $user));

        $user->refresh();
        $this->assertTrue($user->hasRole($role));
        $this->assertTrue($user->hasDirectPermission($direct));

        $this->patch(route('admin.users.update', $user), [
            'name' => 'Updated Planner', 'email' => $user->email, 'password' => '',
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame('Updated Planner', $user->fresh()->name);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delegated_admin_cannot_grant_permissions_they_do_not_hold_or_modify_bootstrap_admin(): void
    {
        $admin = $this->seedAdmin();
        $delegate = User::factory()->create()->givePermissionTo('admin users', 'admin permissions', 'admin roles');
        $otherUser = User::factory()->create();
        $permission = Permission::findByName('admin planning tags', 'web');

        $this->actingAs($delegate)
            ->from(route('admin.users.edit', $otherUser))
            ->patch(route('admin.users.access', $otherUser), ['permissions' => [$permission->id]])
            ->assertSessionHasErrors('permissions.0');

        $this->from(route('admin.roles.create'))
            ->post(route('admin.roles.store'), ['name' => 'Escalated role', 'permissions' => [$permission->id]])
            ->assertSessionHasErrors('permissions.0');

        $this->patch(route('admin.users.update', $admin), ['name' => 'Changed'])->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $delegate))->assertForbidden();
        $this->assertFalse($otherUser->fresh()->can('admin planning tags'));
        $this->assertDatabaseMissing('roles', ['name' => 'Escalated role']);
    }

    public function test_admin_can_create_edit_and_delete_roles_and_custom_permissions(): void
    {
        $this->withoutVite()->actingAs($this->seedAdmin());
        $permission = Permission::findByName('view planning', 'web');

        $this->get(route('admin.roles.create'))->assertOk();
        $this->post(route('admin.roles.store'), [
            'name' => 'Readers', 'permissions' => [$permission->id],
        ])->assertRedirect(route('admin.roles.index'));
        $role = Role::query()->where('name', 'Readers')->sole();
        $this->assertTrue($role->hasPermissionTo($permission));
        $this->get(route('admin.roles.edit', $role))->assertOk();

        $this->patch(route('admin.roles.update', $role), ['name' => 'Read only'])
            ->assertRedirect(route('admin.roles.index'));
        $this->assertSame('Read only', $role->fresh()->name);
        $this->assertFalse($role->fresh()->hasPermissionTo($permission));
        $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        $this->post(route('admin.permissions.store'), ['name' => 'issue exports'])
            ->assertRedirect(route('admin.permissions.index'));
        $custom = Permission::findByName('issue exports', 'web');
        $this->patch(route('admin.permissions.update', $custom), ['name' => 'sprint exports'])
            ->assertRedirect(route('admin.permissions.index'));
        $this->assertSame('sprint exports', $custom->fresh()->name);
        $this->delete(route('admin.permissions.destroy', $permission))->assertForbidden();
        $this->delete(route('admin.permissions.destroy', $custom))->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseMissing('permissions', ['id' => $custom->id]);
    }

    public function test_template_admin_can_add_rename_and_delete_standard_tasks(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create()
            ->givePermissionTo(Permission::findOrCreate('admin planning task templates', 'web'));
        $index = route('admin.task-templates.index');

        $this->actingAs($admin)->post(route('admin.task-templates.store'), ['title' => 'Testen op tablets'])
            ->assertRedirect($index);

        $template = PlanningTaskTemplate::query()->where('title', 'Testen op tablets')->sole();
        $this->get($index)->assertOk()->assertSeeHtml('value="Testen op tablets"');

        $update = route('admin.task-templates.update', $template);
        $this->from($index)->patch($update, ['title' => 'Testen Windows'])->assertSessionHasErrors('title');
        $this->patch($update, ['title' => 'Testen op Android-tablets'])->assertRedirect($index);
        $this->assertSame('Testen op Android-tablets', $template->fresh()->title);

        $this->delete(route('admin.task-templates.destroy', $template))->assertRedirect($index);
        $this->assertModelMissing($template);
    }

    public function test_tag_admin_can_create_rename_and_delete_tags_across_issues(): void
    {
        $this->withoutVite()->actingAs(User::factory()->create()->givePermissionTo(
            Permission::findOrCreate('admin planning tags', 'web'),
        ));

        $this->post(route('admin.tags.store'), ['name' => 'Design Session'])->assertRedirect(route('admin.tags.index'));
        $tag = PlanningTag::query()->sole();
        $issue = PlanningIssue::factory()->create();
        $issue->tags()->attach($tag);

        $this->get(route('admin.tags.index'))->assertOk()->assertSeeText('1 issue');
        $this->patch(route('admin.tags.update', $tag), ['name' => 'Design review'])->assertRedirect(route('admin.tags.index'));
        $this->assertSame(['Design review'], $issue->tags()->pluck('planning_tags.name')->all());

        $this->delete(route('admin.tags.destroy', $tag))->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('planning_issues', ['id' => $issue->id]);
        $this->assertDatabaseEmpty('planning_issue_planning_tag');
    }

    public function test_tag_migration_preserves_existing_issue_tags_and_merges_case_variants(): void
    {
        $firstIssue = PlanningIssue::factory()->create();
        $secondIssue = PlanningIssue::factory()->create();
        $migration = require database_path('migrations/2026_11_27_224656_normalize_planning_tags.php');
        $migration->down();

        DB::table('planning_issues')->where('id', $firstIssue->id)->update([
            'tags' => json_encode(['Design Session', 'Requires testing'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('planning_issues')->where('id', $secondIssue->id)->update([
            'tags' => json_encode(['design session'], JSON_THROW_ON_ERROR),
        ]);

        $migration->up();

        $this->assertDatabaseCount('planning_tags', 2);
        $this->assertSame(['Design Session', 'Requires testing'], $firstIssue->tags()->pluck('planning_tags.name')->all());
        $this->assertSame(['Design Session'], $secondIssue->tags()->pluck('planning_tags.name')->all());
    }

    private function seedAdmin(): User
    {
        config([
            'admin.name' => 'Bootstrap Admin',
            'admin.email' => 'admin@example.test',
            'admin.password' => 'InitialAdminPassword123',
        ]);
        $this->seed(AdminUserSeeder::class);

        return User::query()->where('email', 'admin@example.test')->sole();
    }
}
