<?php

namespace Tests\Feature\Assets;

use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Membership;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Brand roles and their segregation-of-duties rules
 * (docs/roles-and-permissions.md).
 */
class BrandAccessTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);
        $this->manager = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->manager, BrandRoleName::Manager);
    }

    public function test_a_brand_manager_gives_someone_a_role_and_they_can_then_see_the_brand()
    {
        $member = $this->addMember($this->workspace);

        $this->actingAs($member->user)
            ->get(route('workspaces.brands.show', [$this->workspace, $this->brand]))
            ->assertNotFound();

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $member->id,
                'role' => 'viewer',
            ])
            ->assertRedirect(route('workspaces.brands.access.index', [$this->workspace, $this->brand]));

        $this->actingAs($member->user)
            ->get(route('workspaces.brands.show', [$this->workspace, $this->brand]))
            ->assertOk();
    }

    public function test_the_access_page_lists_the_workspace_people_and_their_roles()
    {
        $this->actingAs($this->manager->user)
            ->get(route('workspaces.brands.access.index', [$this->workspace, $this->brand]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('brands/access')
                ->has('people', 2)
                ->where('people.0.managesAll', true)
                ->where('people.1.role', 'manager')
                ->where('people.1.isYou', true));
    }

    public function test_nobody_changes_their_own_brand_role()
    {
        $this->actingAs($this->manager->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $this->manager->id,
                'role' => 'viewer',
            ])
            ->assertForbidden();

        $own = $this->inWorkspace($this->workspace, fn () => BrandRole::sole());

        $this->actingAs($this->manager->user)
            ->delete(route('workspaces.brands.access.destroy', [$this->workspace, $this->brand, $own]))
            ->assertForbidden();
    }

    public function test_editors_and_viewers_cannot_manage_access()
    {
        $editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $editor, BrandRoleName::Editor);
        $other = $this->addMember($this->workspace);

        $this->actingAs($editor->user)
            ->get(route('workspaces.brands.access.index', [$this->workspace, $this->brand]))
            ->assertForbidden();

        $this->actingAs($editor->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $other->id,
                'role' => 'editor',
            ])
            ->assertForbidden();
    }

    public function test_partners_are_never_brand_managers()
    {
        $partner = $this->addMember($this->workspace, kind: MembershipKind::Partner);

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $partner->id,
                'role' => 'manager',
            ])
            ->assertSessionHasErrors('role');

        // The database refuses it too, whatever the app does.
        $this->inWorkspace($this->workspace, function () use ($partner) {
            try {
                DB::transaction(fn () => DB::table('brand_roles')->insert([
                    'id' => (string) Str::uuid7(),
                    'workspace_id' => $this->workspace->id,
                    'brand_id' => $this->brand->id,
                    'membership_id' => $partner->id,
                    'role' => 'manager',
                ]));
                $this->fail('The database made a partner a Brand manager.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('partners cannot be brand managers', $e->getMessage());
            }

            $this->assertFalse(BrandRole::where('membership_id', $partner->id)->exists());
        });
    }

    public function test_owners_and_admins_are_not_given_brand_roles()
    {
        $admin = $this->addMember($this->workspace, WorkspaceRole::Admin);

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $admin->id,
                'role' => 'viewer',
            ])
            ->assertSessionHasErrors('membership_id');
    }

    public function test_a_role_is_changed_and_removed()
    {
        $member = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $member, BrandRoleName::Viewer);

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.brands.access.store', [$this->workspace, $this->brand]), [
                'membership_id' => $member->id,
                'role' => 'editor',
            ]);

        $role = $this->inWorkspace($this->workspace, fn () => BrandRole::where('membership_id', $member->id)->sole());
        $this->assertSame(BrandRoleName::Editor, $role->role);

        $this->actingAs($this->manager->user)
            ->delete(route('workspaces.brands.access.destroy', [$this->workspace, $this->brand, $role]))
            ->assertRedirect();

        $this->inWorkspace($this->workspace, fn () => $this->assertNull($role->fresh()));
    }

    public function test_a_role_on_another_brand_cannot_be_removed_through_this_brand()
    {
        $otherBrand = $this->createBrand($this->workspace, 'Sister brand');
        $member = $this->addMember($this->workspace);
        $role = $this->giveBrandRole($otherBrand, $member, BrandRoleName::Viewer);

        $this->actingAs($this->manager->user)
            ->delete(route('workspaces.brands.access.destroy', [$this->workspace, $this->brand, $role]))
            ->assertNotFound();

        $this->inWorkspace($this->workspace, fn () => $this->assertNotNull($role->fresh()));
    }
}
