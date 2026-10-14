<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells Postgres who is signed in, and clears every tenancy setting once the
 * response is sent so nothing carries over on a reused connection.
 */
class IdentifyDatabaseUser
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancy->reset();

        $userId = $request->user()?->getAuthIdentifier();

        if (is_int($userId)) {
            $this->tenancy->setUser($userId);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenancy->reset();
    }
}
