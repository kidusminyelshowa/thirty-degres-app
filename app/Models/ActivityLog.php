<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Who did what in a workspace. Rows can be added, never changed or deleted:
 * the database refuses updates and deletes, even from the table owner.
 *
 * @property string $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed> $details
 * @property CarbonImmutable $created_at
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'details'])]
class ActivityLog extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    /**
     * @param  array<string, mixed>  $details
     */
    public static function record(string $action, ?Model $subject = null, array $details = [], ?User $actor = null): self
    {
        $actorId = $actor !== null ? $actor->id : auth()->id();

        return self::create([
            'actor_id' => is_int($actorId) ? $actorId : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'details' => $details,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }
}
