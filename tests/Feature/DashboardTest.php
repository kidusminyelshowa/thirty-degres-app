<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_people_without_a_workspace_are_asked_to_create_one()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('workspaces.create'));
    }

    public function test_people_land_in_their_workspace()
    {
        $workspace = $this->createWorkspace('Ethio Coffee');

        $this->actingAs($this->ownerOf($workspace))
            ->get(route('dashboard'))
            ->assertRedirect(route('workspaces.show', $workspace));

        $this->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('workspaces/show')
                ->where('currentWorkspace.name', 'Ethio Coffee')
                ->where('currentWorkspace.role', 'owner')
                ->where('workspaces.0.slug', $workspace->slug));
    }
}
