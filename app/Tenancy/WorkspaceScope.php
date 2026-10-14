<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters every query on company data to the current workspace. With no
 * current workspace it returns nothing, the same way the database does.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class WorkspaceScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = app(Tenancy::class)->workspaceId();

        if ($workspaceId === null) {
            $builder->whereRaw('false');

            return;
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);
    }
}
