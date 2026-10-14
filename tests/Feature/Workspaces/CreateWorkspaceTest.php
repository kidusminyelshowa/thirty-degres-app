<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class CreateWorkspaceTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_a_signed_in_person_can_create_another_workspace()
    {
        $user = User::factory()->create();
        $this->createWorkspace('First Company', $user);

        $this->actingAs($user)->get(route('workspaces.create'))->assertOk();

        $this->post(route('workspaces.store'), ['name' => 'Second Company'])
            ->assertRedirect('/w/second-company');

        $memberships = $this->membershipsOf($user);
        $this->assertSame(['First Company', 'Second Company'], $memberships->pluck('workspace.name')->sort()->values()->all());
        $this->assertSame([WorkspaceRole::Owner, WorkspaceRole::Owner], $memberships->pluck('role')->all());
    }

    public function test_a_name_another_company_already_uses_still_gets_its_own_address()
    {
        $first = $this->createWorkspace('Ethio Coffee');
        $second = $this->createWorkspace('Ethio Coffee');

        $this->assertSame('ethio-coffee', $first->slug);
        $this->assertMatchesRegularExpression('/^ethio-coffee-[a-z0-9]{5}$/', $second->slug);
    }

    public function test_creating_a_workspace_is_logged()
    {
        $workspace = $this->createWorkspace('Ethio Coffee');

        $this->inWorkspace($workspace, function () use ($workspace) {
            $entry = ActivityLog::sole();
            $this->assertSame('workspace.created', $entry->action);
            $this->assertSame($this->ownerOf($workspace)->id, $entry->actor_id);
        });
    }
}
