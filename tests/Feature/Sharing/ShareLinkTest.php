<?php

namespace Tests\Feature\Sharing;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\Membership;
use App\Models\ShareLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesGuidelines;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Read-only share links to a brand's guidelines or a kit of files (THI-38),
 * with the share link rules in docs/roles-and-permissions.md.
 */
class ShareLinkTest extends TestCase
{
    use CreatesAssets, CreatesGuidelines, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $editor;

    private Asset $logo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace, 'Yirgacheffe Gold');
        $this->editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->editor, BrandRoleName::Editor);
        $this->logo = $this->uploadAsset($this->brand, status: AssetStatus::Approved);
    }

    public function test_an_editor_makes_a_link_that_is_shown_once_and_stored_only_as_a_hash()
    {
        $this->publishedPageShowing($this->logo);

        $this->actingAs($this->editor->user)
            ->post($this->storeRoute(), ['name' => 'For the press', 'kind' => 'guidelines', 'days' => 14])
            ->assertRedirect(route('workspaces.brands.share-links.index', [$this->workspace, $this->brand]));

        $url = $this->createdUrl();
        $token = basename($url);

        $link = $this->inWorkspace($this->workspace, fn () => ShareLink::sole());
        $this->assertSame(ShareLink::hashToken($token), $link->token_hash);
        $this->assertStringNotContainsString($token, json_encode($link->getAttributes()) ?: '');
        $this->assertFalse($link->allow_originals);
        $this->assertEqualsWithDelta(now()->addDays(14)->getTimestamp(), $link->expires_at->getTimestamp(), 5);
        $this->inWorkspace($this->workspace, fn () => $this->assertTrue(ActivityLog::where('action', 'share_link.created')->exists()));

        $this->get(route('workspaces.brands.share-links.index', [$this->workspace, $this->brand]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('brands/share-links')
                ->has('links', 1)
                ->where('links.0.name', 'For the press')
                ->where('links.0.isLive', true)
                ->where('links.0.canRevoke', true));
    }

    public function test_links_never_last_more_than_ninety_days_and_only_share_approved_files_of_the_brand()
    {
        $draft = $this->uploadAsset($this->brand, uploader: $this->editor->user);
        $otherBrand = $this->createBrand($this->workspace, 'Sidamo Reserve');
        $otherFile = $this->uploadAsset($otherBrand, status: AssetStatus::Approved);
        $rival = $this->createWorkspace('Rival Roasters');
        $rivalFile = $this->uploadAsset($this->createBrand($rival, 'Rival Blend'), status: AssetStatus::Approved);

        $this->actingAs($this->editor->user);

        $this->post($this->storeRoute(), $this->kit([$this->logo->id], ['days' => 91]))->assertSessionHasErrors('days');
        $this->post($this->storeRoute(), $this->kit([$draft->id]))->assertSessionHasErrors('asset_ids');
        $this->post($this->storeRoute(), $this->kit([$otherFile->id]))->assertSessionHasErrors('asset_ids');
        $this->post($this->storeRoute(), $this->kit([$rivalFile->id]))->assertSessionHasErrors('asset_ids');
        $this->post($this->storeRoute(), $this->kit([]))->assertSessionHasErrors('asset_ids');
        // Guidelines can only be shared once something is published.
        $this->post($this->storeRoute(), ['name' => 'Guidelines', 'kind' => 'guidelines', 'days' => 14])->assertSessionHasErrors('kind');

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, ShareLink::count()));
    }

    public function test_viewers_and_partners_cannot_make_or_see_links()
    {
        $viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $viewer, BrandRoleName::Viewer);
        $partner = $this->addMember($this->workspace, kind: MembershipKind::Partner);
        $this->giveBrandRole($this->brand, $partner, BrandRoleName::Editor);
        $outsider = $this->addMember($this->workspace);

        foreach ([$viewer, $partner] as $member) {
            $this->actingAs($member->user)->post($this->storeRoute(), $this->kit([$this->logo->id]))->assertForbidden();
            $this->get(route('workspaces.brands.share-links.index', [$this->workspace, $this->brand]))->assertForbidden();
        }

        $this->actingAs($outsider->user)->get(route('workspaces.brands.share-links.index', [$this->workspace, $this->brand]))->assertNotFound();
        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, ShareLink::count()));
    }

    public function test_someone_without_an_account_reads_the_published_guidelines_and_nothing_else()
    {
        $draftLogo = $this->uploadAsset($this->brand, uploader: $this->editor->user);
        $unusedFile = $this->uploadAsset($this->brand, status: AssetStatus::Approved);
        $published = $this->publishedPageShowing($this->logo, $draftLogo);
        $draftPage = $this->createGuidelinePage($this->brand, title: 'Colour', position: 1);
        $token = $this->makeLink(['name' => 'For the press', 'kind' => 'guidelines', 'days' => 14]);

        $this->get(route('share.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shared/guidelines')
                ->where('link.brand.name', 'Yirgacheffe Gold')
                ->has('pages', 1)
                ->where('page.id', $published->id)
                ->has('assets', 1)
                ->has("assets.{$this->logo->id}")
                ->where("assets.{$this->logo->id}.downloadUrl", null)
                ->where('currentWorkspace', null)
                ->where('workspaces', []));

        $this->get(route('share.page', [$token, $draftPage->id]))->assertNotFound();
        $this->get(route('share.preview', [$token, $this->logo->id]))->assertOk();
        // Previews only: the original stays home.
        $this->get(route('share.download', [$token, $this->logo->id]))->assertNotFound();
        $this->get(route('share.preview', [$token, $draftLogo->id]))->assertNotFound();
        $this->get(route('share.preview', [$token, $unusedFile->id]))->assertNotFound();

        $this->inWorkspace($this->workspace, function () {
            $this->assertSame(1, ActivityLog::where('action', 'share_link.opened')->count());
            $this->assertNotNull(ShareLink::sole()->last_opened_at);
        });
    }

    public function test_a_kit_shows_only_its_files_and_downloads_originals_when_allowed_and_logged()
    {
        $notInKit = $this->uploadAsset($this->brand, status: AssetStatus::Approved);
        $token = $this->makeLink($this->kit([$this->logo->id], ['allow_originals' => '1']));

        $this->get(route('share.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('shared/files')
                ->has('files', 1)
                ->where('files.0.id', $this->logo->id)
                ->where('link.allowOriginals', true));

        $this->get(route('share.download', [$token, $this->logo->id]))->assertOk()->assertDownload('primary-logo.png');
        $this->get(route('share.download', [$token, $notInKit->id]))->assertNotFound();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(1, ActivityLog::where('action', 'share_link.downloaded')->count()));

        // Archiving a file takes it out of every link.
        $this->inWorkspace($this->workspace, function () {
            $this->logo->status = AssetStatus::Archived;
            $this->logo->save();
        });

        $this->get(route('share.show', $token))->assertInertia(fn (Assert $page) => $page->has('files', 0));
        $this->get(route('share.download', [$token, $this->logo->id]))->assertNotFound();
    }

    public function test_a_password_is_asked_before_anything_is_shown()
    {
        $token = $this->makeLink($this->kit([$this->logo->id], ['password' => 'buna-1234']));

        $this->get(route('share.show', $token))
            ->assertInertia(fn (Assert $page) => $page->component('shared/locked')->missing('files'));
        $this->get(route('share.preview', [$token, $this->logo->id]))->assertNotFound();

        $this->post(route('share.unlock', $token), ['password' => 'wrong-one'])->assertSessionHasErrors('password');
        $this->post(route('share.unlock', $token), ['password' => 'buna-1234'])->assertRedirect(route('share.show', $token));

        $this->get(route('share.show', $token))->assertInertia(fn (Assert $page) => $page->component('shared/files'));
        $this->get(route('share.preview', [$token, $this->logo->id]))->assertOk();
    }

    public function test_expired_revoked_and_unknown_links_are_not_found()
    {
        $expiring = $this->makeLink($this->kit([$this->logo->id], ['days' => 1]));
        $revoked = $this->makeLink($this->kit([$this->logo->id]));

        $link = $this->inWorkspace($this->workspace, fn () => ShareLink::where('token_hash', ShareLink::hashToken($revoked))->sole());
        $this->actingAs($this->editor->user)
            ->delete(route('workspaces.brands.share-links.destroy', [$this->workspace, $this->brand, $link]))
            ->assertRedirect();
        auth()->logout();

        $this->get(route('share.show', $revoked))->assertNotFound();
        $this->get(route('share.preview', [$revoked, $this->logo->id]))->assertNotFound();
        $this->get(route('share.show', str_repeat('a', 40)))->assertNotFound();

        $this->get(route('share.show', $expiring))->assertOk();
        $this->travel(2)->days();
        $this->get(route('share.show', $expiring))->assertNotFound();
    }

    public function test_only_the_maker_or_a_brand_manager_can_turn_a_link_off()
    {
        $token = $this->makeLink($this->kit([$this->logo->id]));
        $link = $this->inWorkspace($this->workspace, fn () => ShareLink::where('token_hash', ShareLink::hashToken($token))->sole());
        $otherEditor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $otherEditor, BrandRoleName::Editor);
        $route = route('workspaces.brands.share-links.destroy', [$this->workspace, $this->brand, $link]);

        $this->actingAs($otherEditor->user)->delete($route)->assertForbidden();
        $this->actingAs($this->ownerOf($this->workspace))->delete($route)->assertRedirect();

        $this->inWorkspace($this->workspace, fn () => $this->assertNotNull($link->fresh()?->revoked_at));
    }

    public function test_a_link_from_one_company_never_serves_another_companys_files()
    {
        $rival = $this->createWorkspace('Rival Roasters');
        $rivalFile = $this->uploadAsset($this->createBrand($rival, 'Rival Blend'), status: AssetStatus::Approved);
        $token = $this->makeLink($this->kit([$this->logo->id], ['allow_originals' => '1']));

        $this->get(route('share.preview', [$token, $rivalFile->id]))->assertNotFound();
        $this->get(route('share.download', [$token, $rivalFile->id]))->assertNotFound();

        // Another company's people cannot list or turn off this brand's links.
        $link = $this->inWorkspace($this->workspace, fn () => ShareLink::sole());
        $this->actingAs($this->ownerOf($rival))
            ->delete(route('workspaces.brands.share-links.destroy', [$rival, $this->brand, $link]))
            ->assertNotFound();
        $this->inWorkspace($this->workspace, fn () => $this->assertNull($link->fresh()?->revoked_at));
    }

    private function storeRoute(): string
    {
        return route('workspaces.brands.share-links.store', [$this->workspace, $this->brand]);
    }

    /**
     * @param  list<string>  $assetIds
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function kit(array $assetIds, array $overrides = []): array
    {
        return ['name' => 'Logo pack', 'kind' => 'files', 'asset_ids' => $assetIds, 'days' => 14, ...$overrides];
    }

    /**
     * Make a link as the brand's Editor and return its secret, signed out.
     *
     * @param  array<string, mixed>  $input
     */
    private function makeLink(array $input): string
    {
        $this->actingAs($this->editor->user)->post($this->storeRoute(), $input)->assertSessionHasNoErrors();
        $token = basename($this->createdUrl());

        auth()->logout();
        $this->flushSession();

        return $token;
    }

    private function createdUrl(): string
    {
        /** @var array{shareLink: array{url: string}} $flash */
        $flash = session(SessionKey::FLASH_DATA);

        return $flash['shareLink']['url'];
    }

    private function publishedPageShowing(Asset ...$assets): GuidelinePage
    {
        return $this->createGuidelinePage($this->brand, blocks: [[
            'id' => 'logos',
            'type' => 'logos',
            'items' => array_map(fn (Asset $asset) => ['assetId' => $asset->id, 'caption' => [], 'background' => 'light'], $assets),
        ]], publisher: User::factory()->create());
    }
}
