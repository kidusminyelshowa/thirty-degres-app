<?php

namespace App\Sharing;

use App\Models\ShareLink;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;

/**
 * The narrow doorway a share link opens (docs/roles-and-permissions.md,
 * "Things that legitimately cross workspaces"): Postgres shows the one link
 * whose secret the request carries, and the request then continues inside
 * that link's workspace with nobody signed in to it.
 */
class OpenShareLink
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * The live link behind a secret, or null for an unknown, expired or
     * revoked one.
     */
    public function find(string $token): ?ShareLink
    {
        $hash = ShareLink::hashToken($token);
        $this->tenancy->setShareTokenHash($hash);

        $link = ShareLink::query()
            ->withoutGlobalScopes()
            ->where('token_hash', $hash)
            ->first();

        $this->tenancy->setShareTokenHash(null);

        if ($link === null || ! $link->isLive()) {
            return null;
        }

        return $this->tenancy->enterForShareLink($link->workspace_id) !== null ? $link : null;
    }

    public function isUnlocked(Request $request, ShareLink $link): bool
    {
        return ! $link->hasPassword() || $request->session()->get($this->sessionKey($link)) === true;
    }

    public function unlock(Request $request, ShareLink $link): void
    {
        $request->session()->put($this->sessionKey($link), true);
    }

    private function sessionKey(ShareLink $link): string
    {
        return "share_links.{$link->id}.unlocked";
    }
}
