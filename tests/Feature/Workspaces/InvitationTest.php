<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = $this->createWorkspace('Ethio Coffee');
    }

    public function test_an_owner_can_invite_a_teammate_by_email()
    {
        Notification::fake();

        $this->actingAs($this->ownerOf($this->workspace))
            ->post(route('workspaces.invitations.store', $this->workspace), [
                'email' => 'Teammate@Example.com',
                'role' => 'admin',
                'kind' => 'staff',
            ])
            ->assertRedirect(route('workspaces.members.index', $this->workspace));

        $invitation = $this->inWorkspace($this->workspace, fn () => Invitation::sole());
        $this->assertSame('teammate@example.com', $invitation->email);
        $this->assertSame(WorkspaceRole::Admin, $invitation->role);

        Notification::assertSentOnDemand(WorkspaceInvitation::class, function (WorkspaceInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
            return $notifiable->routes['mail'] === 'teammate@example.com'
                && Invitation::hashToken($notification->token) === $invitation->token_hash
                && $notification->workspaceName === 'Ethio Coffee';
        });
    }

    public function test_the_invitation_link_shows_the_workspace_to_a_signed_out_person()
    {
        [, $token] = $this->createInvitation($this->workspace);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invitations/show')
                ->where('invitation.workspaceName', 'Ethio Coffee')
                ->where('invitation.email', 'teammate@example.com'));
    }

    public function test_a_new_teammate_signs_up_from_the_link_and_joins_the_workspace()
    {
        [, $token] = $this->createInvitation($this->workspace, role: WorkspaceRole::Admin);

        $this->get(route('invitations.show', $token));

        $this->get(route('register'))
            ->assertInertia(fn ($page) => $page->where('invitation.workspaceName', 'Ethio Coffee'));

        $this->post(route('register.store'), [
            'name' => 'Tigist Haile',
            'email' => 'teammate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'data_transfer_consent' => '1',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'teammate@example.com')->firstOrFail();
        $membership = $this->membershipsOf($user)->sole();

        $this->assertTrue($membership->workspace->is($this->workspace));
        $this->assertSame(WorkspaceRole::Admin, $membership->role);
        $this->assertNotNull($user->data_transfer_consent_at);
        // The emailed link already proved they own the address.
        $this->assertTrue($user->hasVerifiedEmail());

        $this->get(route('dashboard'))->assertRedirect(route('workspaces.show', $this->workspace));
    }

    public function test_an_existing_user_accepts_after_signing_in()
    {
        $user = User::factory()->create(['email' => 'teammate@example.com']);
        [$invitation, $token] = $this->createInvitation($this->workspace);

        $this->actingAs($user)
            ->get(route('invitations.show', $token))
            ->assertInertia(fn ($page) => $page->where('invitation.emailMatches', true));

        // Signing in later sends them back to the invitation first.
        $this->get(route('dashboard'))->assertRedirect(route('invitations.show', $token));

        $this->post(route('invitations.accept', $token))
            ->assertRedirect(route('workspaces.show', $this->workspace));

        $this->inWorkspace($this->workspace, function () use ($user, $invitation) {
            $this->assertSame(WorkspaceRole::Member, Membership::where('user_id', $user->id)->sole()->role);
            $this->assertNotNull($invitation->fresh()?->accepted_at);
        });
    }

    public function test_only_the_invited_email_address_can_accept()
    {
        $someoneElse = User::factory()->create(['email' => 'someone.else@example.com']);
        [, $token] = $this->createInvitation($this->workspace);

        $this->actingAs($someoneElse)
            ->post(route('invitations.accept', $token))
            ->assertSessionHasErrors('invitation');

        $this->inWorkspace($this->workspace, function () use ($someoneElse) {
            $this->assertFalse(Membership::where('user_id', $someoneElse->id)->exists());
        });
    }

    public function test_signing_up_from_an_invitation_with_a_different_email_says_which_address_to_use()
    {
        [, $token] = $this->createInvitation($this->workspace);
        $this->get(route('invitations.show', $token));

        $this->post(route('register.store'), [
            'name' => 'Someone Else',
            'email' => 'someone.else@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'data_transfer_consent' => '1',
        ])->assertSessionHasErrors([
            'email' => 'This invitation was sent to teammate@example.com. Sign up with that address to join Ethio Coffee.',
        ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone.else@example.com']);
    }

    public function test_expired_used_and_unknown_links_do_not_work()
    {
        $user = User::factory()->create(['email' => 'teammate@example.com']);
        [, $expired] = $this->createInvitation($this->workspace, expiresAt: now()->subMinute());

        $this->get(route('invitations.show', $expired))
            ->assertInertia(fn ($page) => $page->where('invitation', null));
        $this->get(route('invitations.show', 'not-a-real-token'))
            ->assertInertia(fn ($page) => $page->where('invitation', null));

        [, $token] = $this->createInvitation($this->workspace);
        $this->actingAs($user)->post(route('invitations.accept', $token));
        $this->inWorkspace($this->workspace, fn () => Membership::where('user_id', $user->id)->delete());

        $this->post(route('invitations.accept', $token))->assertRedirect(route('invitations.show', $token));
        $this->inWorkspace($this->workspace, function () use ($user) {
            $this->assertFalse(Membership::where('user_id', $user->id)->exists());
        });
    }

    public function test_someone_elses_invitation_does_not_keep_redirecting_them()
    {
        $user = User::factory()->create(['email' => 'someone.else@example.com']);
        $own = $this->createWorkspace('Their Own Company', $user);
        [, $token] = $this->createInvitation($this->workspace);

        $this->actingAs($user)->get(route('invitations.show', $token))
            ->assertInertia(fn ($page) => $page->where('invitation.emailMatches', false));

        $this->get(route('dashboard'))->assertRedirect(route('workspaces.show', $own));
        $this->get(route('dashboard'))->assertRedirect(route('workspaces.show', $own));
    }

    public function test_an_invitation_link_reveals_nothing_else_about_the_workspace()
    {
        $this->addMember($this->workspace);
        [, $token] = $this->createInvitation($this->workspace);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('members')->where('currentWorkspace', null));
    }

    public function test_an_admin_can_cancel_a_pending_invitation()
    {
        [$invitation] = $this->createInvitation($this->workspace);
        $admin = $this->addMember($this->workspace, WorkspaceRole::Admin);

        $this->actingAs($admin->user)
            ->delete(route('workspaces.invitations.destroy', [$this->workspace, $invitation]))
            ->assertRedirect();

        $this->inWorkspace($this->workspace, function () {
            $this->assertSame(0, Invitation::count());
        });
    }
}
