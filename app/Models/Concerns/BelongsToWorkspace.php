<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Tenancy\Tenancy;
use App\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For every model that holds a company's data: queries only ever see the
 * current workspace, and new rows are filed under it.
 *
 * @property string $workspace_id
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (self $model): void {
            $current = app(Tenancy::class)->workspaceId();

            if ($current === null) {
                throw new LogicException('Company data can only be created inside a workspace.');
            }

            if (isset($model->workspace_id) && $model->workspace_id !== $current) {
                throw new LogicException('Company data cannot be created for another workspace.');
            }

            $model->workspace_id = $current;
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
