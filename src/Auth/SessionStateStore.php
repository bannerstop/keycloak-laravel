<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Login\PendingLogin;
use Bannerstop\Keycloak\Login\StateStore;
use Illuminate\Contracts\Session\Session;

/**
 * Pending logins in the Laravel session, at most five at a time.
 */
final class SessionStateStore implements StateStore
{
    private const KEY = 'keycloak.logins';
    private const MAX_PENDING = 5;

    public function __construct(
        private readonly Session $session,
    ) {
    }

    public function save(PendingLogin $login): void
    {
        $pending = $this->all();
        $pending[$login->getState()] = $login->toArray();
        $this->session->put(self::KEY, array_slice($pending, -self::MAX_PENDING, null, true));
    }

    public function take(string $state): ?PendingLogin
    {
        $pending = $this->all();
        $data = $pending[$state] ?? null;
        unset($pending[$state]);
        $this->session->put(self::KEY, $pending);

        return is_array($data) ? PendingLogin::fromArray($data) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        $pending = $this->session->get(self::KEY, []);

        return is_array($pending) ? $pending : [];
    }
}
