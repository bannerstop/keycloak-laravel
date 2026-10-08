<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Http\Middleware;

use Bannerstop\Keycloak\Session\KeycloakSession;
use Bannerstop\Keycloak\Session\SessionCheck;
use Bannerstop\KeycloakLaravel\Http\Controllers\KeycloakController;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "keycloak.session": keeps a session from outliving its
 * Keycloak session (back-channel logouts and, with
 * keycloak.session.check_interval, the periodic refresh check). Sessions that
 * were not opened by a Keycloak login are left alone.
 *
 * Parallel requests of one session may both run the refresh check; that only
 * costs a second token request.
 */
final class EnsureKeycloakSessionIsValid
{
    public function __construct(
        private readonly SessionCheck $check,
        private readonly AuthFactory $auth,
        private readonly Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->hasSession() || null === $request->user()) {
            return $next($request);
        }
        $session = KeycloakSession::fromArray((array) $request->session()->get(KeycloakController::SESSION, []));
        if (null === $session) {
            return $next($request);
        }

        $checked = $this->check->check($session);
        if (null === $checked) {
            return $this->endSession($request);
        }
        $request->session()->put(KeycloakController::SESSION, $checked->toArray());

        return $next($request);
    }

    private function endSession(Request $request): Response
    {
        $this->auth->guard($this->config->get('keycloak.login.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Inertia: a full page load of the same URL, where the auth middleware sends the user to the login.
        if ($request->headers->has('X-Inertia')) {
            return new Response('', Response::HTTP_CONFLICT, ['X-Inertia-Location' => $request->fullUrl()]);
        }
        if ($request->expectsJson()) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->to($request->isMethod('GET') ? $request->fullUrl() : route('login'));
    }
}
