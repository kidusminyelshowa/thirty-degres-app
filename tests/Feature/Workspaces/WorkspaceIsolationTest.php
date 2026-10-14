<?php

namespace Tests\Feature\Workspaces;

use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Lock 1 and 2 over HTTP: an Owner of company A tries every workspace route
 * with company B's addresses and ids. Each must answer "not found" and
 * change nothing (docs/roles-and-permissions.md, "Tests that must exist" 1).
 */
class WorkspaceIsolationTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private Workspace $companyA;

    private Workspace $companyB;

    private User $ownerOfA;

    private Membership $memberOfB;

    private Invitation $invitationOfB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->createWorkspace('Company A');
        $this->companyB = $this->createWorkspace('Company B');
        $this->ownerOfA = $this->ownerOf($this->companyA);
        $this->memberOfB = $this->addMember($this->companyB, WorkspaceRole::Admin);
        [$this->invitationOfB] = $this->createInvitation($this->companyB, 'someone@companyb.et');
    }

    public function test_another_companys_workspace_pages_are_not_found()
    {
        $this->actingAs($this->ownerOfA);

        $this->get(route('workspaces.show', $this->companyB))->assertNotFound();
        $this->get(route('workspaces.members.index', $this->companyB))->assertNotFound();
    }

    public function test_another_companys_people_cannot_be_changed_or_removed()
    {
        $this->actingAs($this->ownerOfA);

        $this->patch(route('workspaces.members.update', [$this->companyB, $this->memberOfB]), ['role' => 'member'])->assertNotFound();
        $this->delete(route('workspaces.members.destroy', [$this->companyB, $this->memberOfB]))->assertNotFound();

        // Company B's ids under company A's own address are just as unknown.
        $this->patch(route('workspaces.members.update', [$this->companyA, $this->memberOfB]), ['role' => 'member'])->assertNotFound();
        $this->delete(route('workspaces.members.destroy', [$this->companyA, $this->memberOfB]))->assertNotFound();

        $this->inWorkspace($this->companyB, function () {
            $this->assertSame(WorkspaceRole::Admin, $this->memberOfB->fresh()?->role);
        });
    }

    public function test_another_companys_invitations_cannot_be_cancelled_and_nobody_can_be_invited_there()
    {
        $this->actingAs($this->ownerOfA);

        $this->delete(route('workspaces.invitations.destroy', [$this->companyB, $this->invitationOfB]))->assertNotFound();
        $this->delete(route('workspaces.invitations.destroy', [$this->companyA, $this->invitationOfB]))->assertNotFound();
        $this->post(route('workspaces.invitations.store', $this->companyB), [
            'email' => 'spy@companya.et',
            'role' => 'admin',
            'kind' => 'staff',
        ])->assertNotFound();

        $this->inWorkspace($this->companyB, function () {
            $this->assertNotNull($this->invitationOfB->fresh());
            $this->assertSame(1, Invitation::count());
        });
    }

    public function test_another_companys_people_never_appear_in_shared_page_data()
    {
        $this->actingAs($this->ownerOfA)
            ->get(route('workspaces.members.index', $this->companyA))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('members', 1)
                ->has('invitations', 0)
                ->has('workspaces', 1)
                ->where('workspaces.0.name', 'Company A'));
    }

    public function test_former_and_expired_members_lose_access()
    {
        $expiredPartner = $this->addMember($this->companyA, WorkspaceRole::Member, MembershipKind::Partner, now()->subDay());
        $removed = $this->addMember($this->companyA, WorkspaceRole::Admin);
        $this->inWorkspace($this->companyA, fn () => $removed->delete());

        $this->actingAs($expiredPartner->user)->get(route('workspaces.show', $this->companyA))->assertNotFound();
        $this->actingAs($removed->user)->get(route('workspaces.show', $this->companyA))->assertNotFound();
    }

    public function test_guests_are_sent_to_log_in()
    {
        $this->get(route('workspaces.show', $this->companyA))->assertRedirect(route('login'));
    }

    public function test_malformed_ids_are_not_found()
    {
        $this->actingAs($this->ownerOfA)
            ->delete('/w/'.$this->companyA->slug.'/members/not-a-uuid')
            ->assertNotFound();
    }
}
