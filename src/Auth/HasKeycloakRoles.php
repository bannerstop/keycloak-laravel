<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

/**
 * Users that carry the roles mapped from Keycloak. The "keycloak.role"
 * middleware checks against them.
 */
interface HasKeycloakRoles
{
    /**
     * @return string[]
     */
    public function getKeycloakRoles(): array;
}
