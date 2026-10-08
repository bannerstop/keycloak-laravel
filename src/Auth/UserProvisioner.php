<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Identity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Turns a verified Keycloak identity into the application's user, e.g. by
 * finding or creating an Eloquent model. Link accounts by
 * $identity->getSubject(), not by e-mail address.
 *
 * The returned user must be retrievable by the user provider of the guard
 * the login uses, because Laravel loads it from there on every request.
 */
interface UserProvisioner
{
    /**
     * @param string[] $roles The roles the RoleMapper derived from the identity
     */
    public function provision(Identity $identity, array $roles): Authenticatable;
}
