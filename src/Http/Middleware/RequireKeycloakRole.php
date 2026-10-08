<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Http\Middleware;

use Bannerstop\KeycloakLaravel\Auth\HasKeycloakRoles;
use Closure;
use Illuminate\Http\Request;

/**
 * Route middleware "keycloak.role:admin,editor": the user needs at least one
 * of the roles. Unauthenticated requests should be stopped by "auth" before.
 */
final class RequireKeycloakRole
{
    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (!$user instanceof HasKeycloakRoles || [] === array_intersect($roles, $user->getKeycloakRoles())) {
            abort(403);
        }

        return $next($request);
    }
}
