<?php

namespace Tests\Feature\Workspaces;

use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * The workspace part of the role table and the segregation-of-duties rules
 * in docs/roles-and-permissions.md (tests 4 and 5).
 */
class MemberRolesTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Membership $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->owner = $this->inWorkspace($this->workspace, fn () => Membership::sole());
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function memberListAccess(): array
    {
        return [
            'owner' => ['owner', 200],
            'admin' => ['admin', 200],
            'member' => ['member', 403],
            'partner' => ['partner', 403],
            'expired partner' => ['expired partner', 404],
        ];
    }

    #[DataProvider('memberListAccess')]
    public function test_who_can_see_the_member_list(string $who, int $status)
    {
        $this->actingAs($this->person($who)->user)
            ->get(route('workspaces.members.index', $this->workspace))
            ->assertStatus($status);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function invitePermissions(): array
    {
        return [
            'owner invites an owner' => ['owner', 'owner', 'staff', true],
            'owner invites a partner' => ['owner', 'member', 'partner', true],
            'admin invites an admin' => ['admin', 'admin', 'staff', true],
            'admin invites a member' => ['admin', 'member', 'staff', true],
            'admin cannot invite an owner' => ['admin', 'owner', 'staff', false],
            'member cannot invite' => ['member', 'member', 'staff', false],
            'partner cannot invite' => ['partner', 'member', 'staff', false],
            'partners are never admins' => ['owner', 'admin', 'partner', false],
        ];
    }

    #[DataProvider('invitePermissions')]
    public function test_who_can_invite_whom(string $who, string $role, string $kind, bool $allowed)
    {
        $response = $this->actingAs($this->person($who)->user)
            ->post(route('workspaces.invitations.store', $this->workspace), [
                'email' => 'new.person@example.com',
                'role' => $role,
                'kind' => $kind,
                'partner_organisation' => $kind === 'partner' ? 'Addis Print House' : null,
                'access_ends_at' => $kind === 'partner' ? now()->addDays(90)->toDateString() : null,
            ]);

        $allowed ? $response->assertRedirect(route('workspaces.members.index', $this->workspace)) : $response->assertForbidden();

        $this->inWorkspace($this->workspace, function () use ($allowed) {
            $this->assertSame($allowed ? 1 : 0, Invitation::where('email', 'new.person@example.com')->count());
        });

        $allowed
            ? Notification::assertSentOnDemand(WorkspaceInvitation::class)
            : Notification::assertNothingSent();
    }

    public function test_partner_invitations_need_an_organisation_and_an_end_date()
    {
        $this->actingAs($this->owner->user)
            ->post(route('workspaces.invitations.store', $this->workspace), [
                'email' => 'designer@agency.et',
                'role' => 'member',
                'kind' => 'partner',
            ])
            ->assertSessionHasErrors(['partner_organisation', 'access_ends_at']);
    }

    public function test_people_already_in_the_workspace_are_not_invited_again()
    {
        $member = $this->person('member');

        $this->actingAs($this->owner->user)
            ->post(route('workspaces.invitations.store', $this->workspace), [
                'email' => strtoupper($member->user->email),
                'role' => 'admin',
                'kind' => 'staff',
            ])
            ->assertSessionHasErrors('email');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function roleChanges(): array
    {
        return [
            'owner makes a member admin' => ['owner', 'member', 'admin', true],
            'owner makes an admin owner' => ['owner', 'admin', 'owner', true],
            'admin makes a member admin' => ['admin', 'member', 'admin', true],
            'admin cannot make an owner' => ['admin', 'member', 'owner', false],
            'admin cannot demote an owner' => ['admin', 'other owner', 'member', false],
            'member cannot change roles' => ['member', 'admin', 'member', false],
            'partner cannot become admin' => ['owner', 'partner', 'admin', false],
        ];
    }

    #[DataProvider('roleChanges')]
    public function test_who_can_change_whose_role(string $who, string $target, string $newRole, bool $allowed)
    {
        $actor = $this->person($who);
        $subject = $this->person($target);

        $response = $this->actingAs($actor->user)
            ->patch(route('workspaces.members.update', [$this->workspace, $subject]), ['role' => $newRole]);

        $allowed ? $response->assertRedirect() : $response->assertForbidden();

        $this->inWorkspace($this->workspace, function () use ($subject, $newRole, $allowed) {
            $this->assertSame($allowed, $subject->fresh()?->role->value === $newRole);
        });
    }

    public function test_nobody_changes_their_own_role()
    {
        $admin = $this->person('admin');

        $this->actingAs($admin->user)
            ->patch(route('workspaces.members.update', [$this->workspace, $admin]), ['role' => 'owner'])
            ->assertForbidden();

        $this->actingAs($this->owner->user)
            ->patch(route('workspaces.members.update', [$this->workspace, $this->owner]), ['role' => 'member'])
            ->assertForbidden();
    }

    public function test_the_last_owner_cannot_be_demoted_but_one_of_two_can()
    {
        $secondOwner = $this->person('other owner');

        $this->actingAs($secondOwner->user)
            ->patch(route('workspaces.members.update', [$this->workspace, $this->owner]), ['role' => 'admin'])
            ->assertRedirect();

        // $secondOwner is now the only Owner, and the first is an Admin.
        $this->inWorkspace($this->workspace, fn () => $this->owner->refresh());
        $this->assertSame(WorkspaceRole::Admin, $this->owner->role);

        $this->actingAs($this->owner->user)
            ->delete(route('workspaces.members.destroy', [$this->workspace, $secondOwner]))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function removals(): array
    {
        return [
            'owner removes an admin' => ['owner', 'admin', true],
            'owner removes another owner' => ['owner', 'other owner', true],
            'admin removes a partner' => ['admin', 'partner', true],
            'admin cannot remove an owner' => ['admin', 'other owner', false],
            'member cannot remove anyone' => ['member', 'partner', false],
        ];
    }

    #[DataProvider('removals')]
    public function test_who_can_remove_whom(string $who, string $target, bool $allowed)
    {
        $actor = $this->person($who);
        $subject = $this->person($target);

        $response = $this->actingAs($actor->user)
            ->delete(route('workspaces.members.destroy', [$this->workspace, $subject]));

        $allowed ? $response->assertRedirect() : $response->assertForbidden();

        $this->inWorkspace($this->workspace, function () use ($subject, $allowed) {
            $this->assertSame($allowed, $subject->fresh() === null);
        });
    }

    public function test_nobody_removes_themselves_from_the_member_page()
    {
        $this->actingAs($this->owner->user)
            ->delete(route('workspaces.members.destroy', [$this->workspace, $this->owner]))
            ->assertForbidden();
    }

    public function test_the_last_owner_cannot_delete_their_account()
    {
        $this->actingAs($this->owner->user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($this->owner->user->fresh());
    }

    public function test_role_changes_and_removals_are_logged()
    {
        $member = $this->person('member');
        $admin = $this->person('admin');

        $this->actingAs($this->owner->user);
        $this->patch(route('workspaces.members.update', [$this->workspace, $member]), ['role' => 'admin']);
        $this->delete(route('workspaces.members.destroy', [$this->workspace, $admin]));

        $this->inWorkspace($this->workspace, function () use ($member) {
            $changed = ActivityLog::where('action', 'member.role_changed')->sole();
            $this->assertSame($this->owner->user_id, $changed->actor_id);
            $this->assertSame($member->id, $changed->subject_id);
            $this->assertEquals(['from' => 'member', 'to' => 'admin'], $changed->details);
            $this->assertSame(1, ActivityLog::where('action', 'member.removed')->count());
        });
    }

    private function person(string $who): Membership
    {
        return match ($who) {
            'owner' => $this->owner,
            'other owner' => $this->addMember($this->workspace, WorkspaceRole::Owner),
            'admin' => $this->addMember($this->workspace, WorkspaceRole::Admin),
            'member' => $this->addMember($this->workspace),
            'partner' => $this->addMember($this->workspace, kind: MembershipKind::Partner),
            'expired partner' => $this->addMember($this->workspace, kind: MembershipKind::Partner, accessEndsAt: now()->subDay()),
        };
    }
}
