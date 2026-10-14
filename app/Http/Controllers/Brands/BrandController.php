<?php

namespace App\Http\Controllers\Brands;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('create', Brand::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Sub-brands sit under a top-level brand of this workspace.
            'parent_id' => ['nullable', 'uuid', Rule::exists('brands', 'id')->where('workspace_id', $workspace->id)->whereNull('parent_id')],
        ]);

        $brand = DB::transaction(function () use ($validated) {
            $brand = Brand::create([
                'name' => $validated['name'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);

            ActivityLog::record('brand.created', $brand, ['name' => $brand->name, 'parent_id' => $brand->parent_id]);

            return $brand;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand created.')]);

        return to_route('workspaces.brands.show', [$workspace, $brand]);
    }

    public function update(Request $request, Workspace $workspace, Brand $brand): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('update', $brand);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $previous = $brand->name;

        DB::transaction(function () use ($brand, $validated, $previous) {
            $brand->update(['name' => $validated['name']]);
            ActivityLog::record('brand.renamed', $brand, ['from' => $previous, 'to' => $brand->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand renamed.')]);

        return to_route('workspaces.brands.show', [$workspace, $brand]);
    }
}
