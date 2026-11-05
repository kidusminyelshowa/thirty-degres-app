<?php

namespace App\Models;

use App\Enums\ShareLinkKind;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A secret, read-only link to a brand's published guidelines or to a kit of
 * its approved files, for people without an account. Only a hash of the
 * link's secret is stored, so reading the database cannot rebuild links.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $brand_id
 * @property string $name
 * @property ShareLinkKind $kind
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $password_hash
 * @property bool $allow_originals
 * @property int|null $created_by
 * @property CarbonImmutable|null $last_opened_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand $brand
 * @property-read User|null $creator
 */
#[Fillable(['brand_id', 'name', 'kind', 'token_hash', 'expires_at', 'password_hash', 'allow_originals', 'created_by'])]
#[Hidden(['token_hash', 'password_hash'])]
class ShareLink extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const DEFAULT_DAYS = 14;

    public const MAX_DAYS = 90;

    public const MAX_FILES = 200;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The files in a kit. Guideline links have none; they show what the
     * published pages use.
     *
     * @return BelongsToMany<Asset, $this>
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'share_link_assets')->withPivot('workspace_id');
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function hasPassword(): bool
    {
        return $this->password_hash !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ShareLinkKind::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'allow_originals' => 'boolean',
        ];
    }
}
