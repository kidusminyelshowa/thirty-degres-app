<?php

namespace App\Models;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Enums\PreviewStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A file in a brand's library: a logo, font, photo or document. The original
 * is stored as uploaded; pages only ever show its light preview.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $brand_id
 * @property string $name
 * @property AssetKind $kind
 * @property AssetStatus $status
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property PreviewStatus $preview_status
 * @property string|null $preview_path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $uploaded_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand $brand
 * @property-read User|null $uploader
 * @property-read User|null $approver
 */
#[Fillable(['brand_id', 'name', 'kind', 'original_name', 'extension', 'mime_type', 'size', 'disk', 'path', 'uploaded_by'])]
class Asset extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'preview_status' => 'none',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('workspace_id')->orderBy('name');
    }

    /**
     * Replace the file's tags with these names, creating any new ones.
     *
     * @param  list<string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = array_map(fn (string $name) => Tag::findOrCreateNamed($name)->id, $names);

        $this->tags()->syncWithPivotValues(array_values(array_unique($ids)), ['workspace_id' => $this->workspace_id]);
    }

    public function isDraft(): bool
    {
        return $this->status === AssetStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === AssetStatus::Approved;
    }

    public function isArchived(): bool
    {
        return $this->status === AssetStatus::Archived;
    }

    /**
     * Where an upload is stored. Never built from the uploaded file's name,
     * which the uploader controls.
     */
    public static function storagePathFor(string $workspaceId, string $assetId, string $extension): string
    {
        return "workspaces/{$workspaceId}/assets/{$assetId}/original.{$extension}";
    }

    public static function previewPathFor(string $workspaceId, string $assetId): string
    {
        return "workspaces/{$workspaceId}/assets/{$assetId}/preview.webp";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'status' => AssetStatus::class,
            'preview_status' => PreviewStatus::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
