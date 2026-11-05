<?php

namespace App\Actions\Workspaces;

use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Emails someone a link to join the current workspace. Inviting the same
 * address again replaces the earlier, unused link. A partner can be invited
 * straight onto one brand, which is then all they see.
 */
class InviteMember
{
    public function handle(
        Workspace $workspace,
        User $inviter,
        string $email,
        WorkspaceRole $role,
        MembershipKind $kind,
        ?string $partnerOrganisation = null,
        ?CarbonInterface $accessEndsAt = null,
        ?Brand $brand = null,
        ?BrandRoleName $brandRole = null,
    ): Invitation {
        $token = Str::random(48);
        $email = Str::lower($email);

        $invitation = DB::transaction(function () use ($inviter, $email, $role, $kind, $partnerOrganisation, $accessEndsAt, $brand, $brandRole, $token) {
            Invitation::query()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->delete();

            $invitation = Invitation::create([
                'email' => $email,
                'role' => $role,
                'kind' => $kind,
                'partner_organisation' => $kind === MembershipKind::Partner ? $partnerOrganisation : null,
                'access_ends_at' => $kind === MembershipKind::Partner ? $accessEndsAt : null,
                'brand_id' => $brand !== null && $brandRole !== null ? $brand->id : null,
                'brand_role' => $brand !== null ? $brandRole : null,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(Invitation::DAYS_VALID),
            ]);

            ActivityLog::record('member.invited', $invitation, [
                'email' => $email,
                'role' => $role->value,
                'kind' => $kind->value,
                'brand_id' => $invitation->brand_id,
                'brand_role' => $invitation->brand_role?->value,
            ]);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(
            new WorkspaceInvitation($workspace->name, $inviter->name, $token, $invitation->expires_at),
        );

        return $invitation;
    }
}
