<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * The file part of the role table in docs/roles-and-permissions.md
 * ("What each role can do"), run for every role. 404 means the person
 * cannot even see the file; 403 means they see it but may not do this.
 */
class AssetRolesTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Asset $approved;

    private Asset $draft;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);

        $author = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $author, BrandRoleName::Editor);

        $this->approved = $this->uploadAsset($this->brand, uploader: $author->user, status: AssetStatus::Approved);
        $this->draft = $this->uploadAsset($this->brand, uploader: $author->user);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function roles(): array
    {
        //                                     see approved, see draft, upload, approve, archive, access
        return [
            'Owner' => ['owner', self::expect(200, 200, 302, 302, 302, 200)],
            'Admin' => ['admin', self::expect(200, 200, 302, 302, 302, 200)],
            'Brand manager' => ['manager', self::expect(200, 200, 302, 302, 302, 200)],
            'Editor' => ['editor', self::expect(200, 200, 302, 403, 403, 403)],
            'Viewer' => ['viewer', self::expect(200, 404, 403, 404, 403, 403)],
            'Partner editor' => ['partner-editor', self::expect(200, 404, 302, 404, 403, 403)],
            'Partner viewer' => ['partner-viewer', self::expect(200, 404, 403, 404, 403, 403)],
            'Member with no brand role' => ['none', self::expect(404, 404, 404, 404, 404, 404)],
        ];
    }

    /**
     * @param  array<string, int>  $expected
     */
    #[DataProvider('roles')]
    public function test_what_each_role_can_do_with_files(string $role, array $expected)
    {
        $this->actingAs($this->person($role));

        $this->get(route('workspaces.assets.show', [$this->workspace, $this->approved]))->assertStatus($expected['see approved']);
        $this->get(route('workspaces.assets.show', [$this->workspace, $this->draft]))->assertStatus($expected['see draft']);
        $this->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
            'file' => UploadedFile::fake()->image('new.png'),
        ])->assertStatus($expected['upload']);
        $this->post(route('workspaces.assets.approve', [$this->workspace, $this->draft]))->assertStatus($expected['approve']);
        $this->post(route('workspaces.assets.archive', [$this->workspace, $this->approved]))->assertStatus($expected['archive']);
        $this->get(route('workspaces.brands.access.index', [$this->workspace, $this->brand]))->assertStatus($expected['access']);
    }

    private function person(string $role): User
    {
        return match ($role) {
            'owner' => $this->ownerOf($this->workspace),
            'admin' => $this->addMember($this->workspace, WorkspaceRole::Admin)->user,
            'none' => $this->addMember($this->workspace)->user,
            default => $this->withBrandRole($role),
        };
    }

    private function withBrandRole(string $role): User
    {
        $isPartner = str_starts_with($role, 'partner-');
        $membership = $this->addMember($this->workspace, kind: $isPartner ? MembershipKind::Partner : MembershipKind::Staff);
        $this->giveBrandRole($this->brand, $membership, BrandRoleName::from(str_replace('partner-', '', $role)));

        return $membership->user;
    }

    /**
     * @return array<string, int>
     */
    private static function expect(int $seeApproved, int $seeDraft, int $upload, int $approve, int $archive, int $access): array
    {
        return [
            'see approved' => $seeApproved,
            'see draft' => $seeDraft,
            'upload' => $upload,
            'approve' => $approve,
            'archive' => $archive,
            'access' => $access,
        ];
    }
}
