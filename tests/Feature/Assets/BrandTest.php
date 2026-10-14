<?php

namespace Tests\Feature\Assets;

use App\Enums\BrandRoleName;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = $this->createWorkspace('Ethio Coffee');
    }

    public function test_an_admin_creates_a_brand_and_a_sub_brand()
    {
        $admin = $this->addMember($this->workspace, WorkspaceRole::Admin)->user;

        $this->actingAs($admin)
            ->post(route('workspaces.brands.store', $this->workspace), ['name' => 'ይርጋጨፌ ጎልድ'])
            ->assertRedirect();

        $brand = $this->inWorkspace($this->workspace, fn () => Brand::sole());
        $this->assertSame('ይርጋጨፌ ጎልድ', $brand->name);

        $this->actingAs($admin)
            ->post(route('workspaces.brands.store', $this->workspace), ['name' => 'Gold Export', 'parent_id' => $brand->id])
            ->assertRedirect(route('workspaces.brands.show', [$this->workspace, $this->inWorkspace($this->workspace, fn () => Brand::where('name', 'Gold Export')->sole())]));

        $this->inWorkspace($this->workspace, function () use ($brand) {
            $this->assertSame($brand->id, Brand::where('name', 'Gold Export')->sole()->parent_id);
            $this->assertSame(2, ActivityLog::where('action', 'brand.created')->count());
        });
    }

    public function test_sub_brands_are_one_level_deep()
    {
        $brand = $this->createBrand($this->workspace);
        $sub = $this->createBrand($this->workspace, 'Gold Export', $brand);

        $this->actingAs($this->ownerOf($this->workspace))
            ->post(route('workspaces.brands.store', $this->workspace), ['name' => 'Too deep', 'parent_id' => $sub->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_members_cannot_create_or_rename_brands_even_as_brand_manager()
    {
        $brand = $this->createBrand($this->workspace);
        $member = $this->addMember($this->workspace);
        $this->giveBrandRole($brand, $member, BrandRoleName::Manager);

        $this->actingAs($member->user)
            ->post(route('workspaces.brands.store', $this->workspace), ['name' => 'Rogue brand'])
            ->assertForbidden();

        $this->actingAs($member->user)
            ->patch(route('workspaces.brands.update', [$this->workspace, $brand]), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(1, Brand::count()));
    }

    public function test_an_admin_renames_a_brand()
    {
        $brand = $this->createBrand($this->workspace);

        $this->actingAs($this->ownerOf($this->workspace))
            ->patch(route('workspaces.brands.update', [$this->workspace, $brand]), ['name' => 'Sidamo Reserve'])
            ->assertRedirect();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame('Sidamo Reserve', $brand->fresh()?->name));
    }

    public function test_a_member_only_sees_the_brands_they_were_given_and_their_sub_brands()
    {
        $given = $this->createBrand($this->workspace, 'Given');
        $sub = $this->createBrand($this->workspace, 'Given Export', $given);
        $other = $this->createBrand($this->workspace, 'Sister brand');
        $member = $this->addMember($this->workspace);
        $this->giveBrandRole($given, $member, BrandRoleName::Viewer);

        $this->actingAs($member->user);

        $this->get(route('workspaces.show', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/show')
                ->has('brands', 2)
                ->where('brands.0.name', 'Given')
                ->where('brands.1.name', 'Given Export')
                ->where('brands.1.role', 'viewer')
                ->where('canCreateBrand', false));

        $this->get(route('workspaces.brands.show', [$this->workspace, $given]))->assertOk();
        $this->get(route('workspaces.brands.show', [$this->workspace, $sub]))->assertOk();
        $this->get(route('workspaces.brands.show', [$this->workspace, $other]))->assertNotFound();
    }

    public function test_a_sub_brand_role_adds_to_the_parent_role_and_never_takes_away()
    {
        $parent = $this->createBrand($this->workspace, 'telebirr');
        $sub = $this->createBrand($this->workspace, 'telebirr Merchant', $parent);
        $member = $this->addMember($this->workspace);
        $this->giveBrandRole($parent, $member, BrandRoleName::Viewer);
        $this->giveBrandRole($sub, $member, BrandRoleName::Editor);

        $this->actingAs($member->user)
            ->get(route('workspaces.brands.show', [$this->workspace, $sub]))
            ->assertInertia(fn (Assert $page) => $page->where('brand.role', 'editor')->where('brand.canUpload', true));

        $this->actingAs($member->user)
            ->get(route('workspaces.brands.show', [$this->workspace, $parent]))
            ->assertInertia(fn (Assert $page) => $page->where('brand.role', 'viewer')->where('brand.canUpload', false));
    }

    public function test_owners_and_admins_manage_every_brand()
    {
        $brand = $this->createBrand($this->workspace);

        $this->actingAs($this->ownerOf($this->workspace))
            ->get(route('workspaces.brands.show', [$this->workspace, $brand]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assets/index')
                ->where('brand.role', 'manager')
                ->where('brand.canUpload', true)
                ->where('brand.canManageAccess', true));
    }
}
