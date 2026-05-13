<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAccessToHotelData
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->hasRole(RoleEnum::PLATFORM_ADMIN) || $user->hasRole(RoleEnum::PLATFORM_SUPPORT)) {
            return $next($request);
        }

        if ($user->isHotelStaff()) {
            return $next($request);
        }

        abort(403, 'Access denied. Hotel staff or support only.');
    }
}
