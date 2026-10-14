<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One page of a brand's guidelines, e.g. "Logo" or "Colour", written in
 * the brand's languages. Editors change the working copy (title and blocks);
 * publishing copies it to the published fields, which is what Viewers see.
 * The block shapes are described in App\Guidelines\GuidelineBlocks.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $brand_id
 * @property int $position
 * @property array<string, string> $title
 * @property list<array<string, mixed>> $blocks
 * @property array<string, string>|null $published_title
 * @property list<array<string, mixed>>|null $published_blocks
 * @property int|null $created_by
 * @property int|null $edited_by
 * @property CarbonImmutable|null $edited_at
 * @property int|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand $brand
 * @property-read User|null $editor
 * @property-read User|null $publisher
 */
#[Fillable(['brand_id', 'position', 'title', 'blocks', 'created_by', 'edited_by', 'edited_at'])]
class GuidelinePage extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => '{}',
        'blocks' => '[]',
    ];

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
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * The working copy differs from what Viewers see.
     */
    public function hasUnpublishedChanges(): bool
    {
        return ! $this->isPublished()
            || $this->published_title != $this->title
            || $this->published_blocks !== $this->blocks;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'title' => 'array',
            'published_title' => 'array',
            'blocks' => 'array',
            'published_blocks' => 'array',
            'edited_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
