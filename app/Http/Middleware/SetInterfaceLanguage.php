<?php

namespace App\Http\Middleware;

use App\Localisation\InterfaceLanguages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows every screen, message and email of this request in the person's
 * interface language (THI-38).
 */
class SetInterfaceLanguage
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(InterfaceLanguages::forRequest($request));

        return $next($request);
    }
}
