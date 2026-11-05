<?php

namespace Tests\Feature\Workspaces;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesGuidelines;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Inviting an agency as a guest (a partner) to one brand (THI-38). Done when
 * a guest account sees only what was shared with it.
 */
class GuestAccessTest extends TestCase
{
    use CreatesAssets, CreatesGuidelines, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $shared;

    private Brand $other;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        Notification::fake();

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->shared = $this->createBrand($this->workspace, 'Yirgacheffe Gold');
        $this->other = $this->createBrand($this->workspace, 'Sidamo Reserve');
    }

    public function test_an_owner_invites_an_agency_onto_one_brand()
    {
        $this->actingAs($this->ownerOf($this->workspace))
            ->post(route('workspaces.invitations.store', $this->workspace), $this->guestInvite())
            ->assertRedirect(route('workspaces.members.index', $this->workspace));

        $invitation = $this->inWorkspace($this->workspace, fn () => Invitation::sole());
        $this->assertSame(MembershipKind::Partner, $invitation->kind);
        $this->assertSame($this->shared->id, $invitation->brand_id);
        $this->assertSame(BrandRoleName::Viewer, $invitation->brand_role);

        Notification::assertSentOnDemand(WorkspaceInvitation::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'designer@agency.et');

        $this->get(route('workspaces.members.index', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.brandName', 'Yirgacheffe Gold')
                ->where('invitations.0.brandRole', 'viewer'));
    }

    public function test_a_guest_cannot_be_invited_as_brand_manager_or_without_a_brand()
    {
        $owner = $this->ownerOf($this->workspace);

        $this->actingAs($owner)
            ->post(route('workspaces.invitations.store', $this->workspace), $this->guestInvite(['brand_role' => 'manager']))
            ->assertSessionHasErrors('brand_role');

        $this->post(route('workspaces.invitations.store', $this->workspace), $this->guestInvite(['brand_id' => null]))
            ->assertSessionHasErrors('brand_id');

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, Invitation::count()));
    }

    public function test_another_companys_brand_cannot_be_named_in_an_invitation()
    {
        $otherCompany = $this->createWorkspace('Rival Roasters');
        $theirBrand = $this->createBrand($otherCompany, 'Rival Blend');

        $this->actingAs($this->ownerOf($this->workspace))
            ->post(route('workspaces.invitations.store', $this->workspace), $this->guestInvite(['brand_id' => $theirBrand->id]))
            ->assertSessionHasErrors('brand_id');

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, Invitation::count()));
        Notification::assertNothingSent();
    }

    public function test_the_invitation_page_names_the_brand_and_accepting_gives_the_role_on_it()
    {
        $token = $this->inviteGuest(BrandRoleName::Editor);
        $guest = User::factory()->create(['email' => 'designer@agency.et']);

        $this->get(route('invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.brandName', 'Yirgacheffe Gold')
                ->where('invitation.brandRole', 'editor')
                ->where('invitation.kind', 'partner'));

        $this->actingAs($guest)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('workspaces.show', $this->workspace));

        $this->inWorkspace($this->workspace, function () use ($guest) {
            $membership = Membership::where('user_id', $guest->id)->sole();
            $this->assertSame(MembershipKind::Partner, $membership->kind);
            $this->assertSame('Addis Design Studio', $membership->partner_organisation);
            $this->assertNotNull($membership->access_ends_at);

            $role = BrandRole::where('membership_id', $membership->id)->sole();
            $this->assertSame($this->shared->id, $role->brand_id);
            $this->assertSame(BrandRoleName::Editor, $role->role);
        });
    }

    public function test_a_guest_sees_only_what_was_shared()
    {
        $approved = $this->uploadAsset($this->shared, status: AssetStatus::Approved);
        $draft = $this->uploadAsset($this->shared);
        $otherBrandFile = $this->uploadAsset($this->other, status: AssetStatus::Approved);
        $publishedPage = $this->createGuidelinePage($this->shared, publisher: User::factory()->create());
        $draftPage = $this->createGuidelinePage($this->shared, title: 'Colour', position: 1);
        $otherBrandPage = $this->createGuidelinePage($this->other, publisher: User::factory()->create());

        $token = $this->inviteGuest();
        $guest = User::factory()->create(['email' => 'designer@agency.et']);
        $this->actingAs($guest)->post(route('invitations.accept', $token));

        $this->get(route('workspaces.show', $this->workspace))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('memberCount', null)
                ->has('brands', 1)
                ->where('brands.0.name', 'Yirgacheffe Gold')
                ->where('brands.0.files', 1)
                ->where('currentWorkspace.canManageMembers', false));

        $this->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 1)
                ->where('assets.data.0.id', $approved->id)
                ->has('brands', 1));

        $this->get(route('workspaces.guidelines.index', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page
                ->has('brands', 1)
                ->has('brands.0.pages', 1)
                ->where('brands.0.pages.0.id', $publishedPage->id));

        $this->get(route('workspaces.assets.show', [$this->workspace, $approved]))->assertOk();
        $this->get(route('workspaces.guidelines.show', [$this->workspace, $publishedPage]))->assertOk();

        // Everything else is "not found", or refused where the address is the workspace's own.
        $this->get(route('workspaces.assets.show', [$this->workspace, $draft]))->assertNotFound();
        $this->get(route('workspaces.assets.download', [$this->workspace, $otherBrandFile]))->assertNotFound();
        $this->get(route('workspaces.brands.show', [$this->workspace, $this->other]))->assertNotFound();
        $this->get(route('workspaces.guidelines.show', [$this->workspace, $draftPage]))->assertNotFound();
        $this->get(route('workspaces.guidelines.show', [$this->workspace, $otherBrandPage]))->assertNotFound();
        $this->get(route('workspaces.members.index', $this->workspace))->assertForbidden();
        $this->get(route('workspaces.brands.access.index', [$this->workspace, $this->shared]))->assertForbidden();
        $this->post(route('workspaces.brands.assets.store', [$this->workspace, $this->shared]), [])->assertForbidden();
        $this->post(route('workspaces.brands.share-links.store', [$this->workspace, $this->shared]), [])->assertForbidden();
    }

    public function test_a_guest_loses_everything_when_their_access_ends()
    {
        $token = $this->inviteGuest();
        $guest = User::factory()->create(['email' => 'designer@agency.et']);
        $this->actingAs($guest)->post(route('invitations.accept', $token));

        $this->travel(91)->days();

        $this->get(route('workspaces.show', $this->workspace))->assertNotFound();
        $this->get(route('workspaces.brands.show', [$this->workspace, $this->shared]))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function guestInvite(array $overrides = []): array
    {
        return [
            'email' => 'designer@agency.et',
            'role' => 'member',
            'kind' => 'partner',
            'partner_organisation' => 'Addis Design Studio',
            'access_ends_at' => now()->addDays(90)->toDateString(),
            'brand_id' => $this->shared->id,
            'brand_role' => 'viewer',
            ...$overrides,
        ];
    }

    private function inviteGuest(BrandRoleName $role = BrandRoleName::Viewer): string
    {
        $this->actingAs($this->ownerOf($this->workspace))
            ->post(route('workspaces.invitations.store', $this->workspace), $this->guestInvite(['brand_role' => $role->value]));

        $token = null;
        Notification::assertSentOnDemand(WorkspaceInvitation::class, function (WorkspaceInvitation $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        auth()->logout();

        return (string) $token;
    }
}
