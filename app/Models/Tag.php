<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A word people use to find files, e.g. "primary logo" or "Ramadan 2026".
 * Tags are shared across a workspace's brands; "Logo" and "logo" are one tag.
 *
 * @property string $id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name'])]
class Tag extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const MAX_LENGTH = 40;

    /**
     * @return BelongsToMany<Asset, $this>
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)->withPivot('workspace_id');
    }

    /**
     * The workspace's tag with this name, created if it does not exist yet.
     */
    public static function findOrCreateNamed(string $name): self
    {
        $existing = self::query()->whereRaw('lower(name) = lower(?)', [$name])->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => self::create(['name' => $name]));
        } catch (UniqueConstraintViolationException) {
            // Someone else created it a moment ago.
            return self::query()->whereRaw('lower(name) = lower(?)', [$name])->firstOrFail();
        }
    }
}
