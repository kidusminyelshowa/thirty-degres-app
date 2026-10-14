<?php

namespace App\Http\Controllers\Brands;

use App\Assets\BrandAccess;
use App\Enums\BrandRoleName;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who works on a brand, and as what. Owners, Admins and the brand's Brand
 * managers decide; nobody changes their own role.
 */
class BrandAccessController extends Controller
{
    public function __construct(private BrandAccess $access) {}

    public function index(Request $request, Workspace $workspace, Brand $brand): Response
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('manageAccess', $brand);

        /** @var User $user */
        $user = $request->user();
        $brand->loadMissing('parent');

        $roles = BrandRole::query()
            ->whereIn('brand_id', array_filter([$brand->id, $brand->parent_id]))
            ->get()
            ->groupBy('membership_id');

        $people = Membership::query()
            ->active()
            ->with('user')
            ->get()
            ->map(function (Membership $membership) use ($brand, $roles, $user) {
                $held = $roles->get($membership->id, collect());
                $direct = $held->firstWhere('brand_id', $brand->id);
                $inherited = $held->firstWhere('brand_id', $brand->parent_id);

                return [
                    'membershipId' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'kind' => $membership->kind->value,
                    'partnerOrganisation' => $membership->partner_organisation,
                    'workspaceRole' => $membership->role->value,
                    'managesAll' => $membership->managesWorkspace(),
                    'role' => $direct?->role->value,
                    'roleId' => $direct?->id,
                    'inheritedRole' => $inherited?->role->value,
                    'isYou' => $membership->user_id === $user->id,
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => [$b['managesAll'], $b['role'] !== null] <=> [$a['managesAll'], $a['role'] !== null],
                fn (array $a, array $b) => $a['name'] <=> $b['name'],
            ])
            ->values();

        return Inertia::render('brands/access', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'parent' => $brand->parent !== null ? ['id' => $brand->parent->id, 'name' => $brand->parent->name] : null,
            ],
            'people' => $people,
        ]);
    }

    public function store(Request $request, Workspace $workspace, Brand $brand): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('manageAccess', $brand);

        $validated = $request->validate([
            'membership_id' => ['required', 'uuid'],
            'role' => ['required', Rule::enum(BrandRoleName::class)],
        ]);

        $membership = Membership::query()->active()->whereKey($request->string('membership_id')->toString())->first();

        if ($membership === null) {
            throw ValidationException::withMessages(['membership_id' => __('This person is not in the workspace.')]);
        }

        $role = BrandRoleName::from($validated['role']);
        $this->guard($request, $membership, $role);

        DB::transaction(function () use ($brand, $membership, $role) {
            $brandRole = BrandRole::query()->firstOrNew([
                'brand_id' => $brand->id,
                'membership_id' => $membership->id,
            ]);
            $previous = $brandRole->exists ? $brandRole->role : null;
            $brandRole->role = $role;
            $brandRole->save();

            ActivityLog::record('brand.role_given', $brandRole, [
                'brand_id' => $brand->id,
                'user_id' => $membership->user_id,
                'from' => $previous?->value,
                'to' => $role->value,
            ]);
        });

        $this->access->flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access updated.')]);

        return to_route('workspaces.brands.access.index', [$workspace, $brand]);
    }

    public function destroy(Request $request, Workspace $workspace, Brand $brand, BrandRole $brandRole): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand) && $brandRole->brand_id === $brand->id, 404);
        Gate::authorize('manageAccess', $brand);

        /** @var User $user */
        $user = $request->user();
        $brandRole->loadMissing('membership');

        if ($brandRole->membership->user_id === $user->id) {
            abort(403, __('Nobody can change their own role.'));
        }

        DB::transaction(function () use ($brandRole, $brand) {
            $brandRole->delete();

            ActivityLog::record('brand.role_removed', $brandRole, [
                'brand_id' => $brand->id,
                'user_id' => $brandRole->membership->user_id,
                'role' => $brandRole->role->value,
            ]);
        });

        $this->access->flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access removed.')]);

        return to_route('workspaces.brands.access.index', [$workspace, $brand]);
    }

    /**
     * The segregation-of-duties rules for brand roles.
     */
    private function guard(Request $request, Membership $membership, BrandRoleName $role): void
    {
        if ($membership->user_id === $request->user()?->getAuthIdentifier()) {
            abort(403, __('Nobody can change their own role.'));
        }

        if ($membership->isPartner() && $role === BrandRoleName::Manager) {
            throw ValidationException::withMessages(['role' => __('Partners can be Editors or Viewers, never Brand managers.')]);
        }

        if ($membership->managesWorkspace()) {
            throw ValidationException::withMessages(['membership_id' => __('Owners and Admins already manage every brand.')]);
        }
    }
}
