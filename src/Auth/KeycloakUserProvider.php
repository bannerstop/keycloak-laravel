<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;

/**
 * The default: KeycloakUser objects kept in the session. Use the "keycloak"
 * user provider driver for the guard the login uses.
 */
final class KeycloakUserProvider implements UserProvider, UserProvisioner
{
    private const SESSION_KEY = 'keycloak.user';

    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function provision(Identity $identity, array $roles): Authenticatable
    {
        $user = KeycloakUser::fromIdentity($identity, $roles);
        $this->session->put(self::SESSION_KEY, $user->toArray());

        return $user;
    }

    /**
     * @param mixed $identifier
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $data = $this->session->get(self::SESSION_KEY);
        $user = is_array($data) ? KeycloakUser::fromArray($data) : null;

        return null !== $user && $user->getAuthIdentifier() === $identifier ? $user : null;
    }

    /**
     * @param mixed  $identifier
     * @param string $token
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    /**
     * @param string $token
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $credentials
     * @param bool                 $force
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, $force = false): void
    {
    }
}
