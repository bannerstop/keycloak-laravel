<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Auth;

use Bannerstop\Keycloak\Bearer\BearerToken;
use Bannerstop\Keycloak\Exception\HttpException;
use Bannerstop\Keycloak\Exception\InvalidTokenException;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Role\RoleMapper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * The "keycloak-bearer" guard driver: the user behind the access token in
 * the Authorization header, or null.
 */
final class BearerTokenResolver
{
    public function __construct(
        private KeycloakClient $client,
        private RoleMapper $roleMapper,
        private UserProvisioner $provisioner,
        private LoggerInterface $logger,
        private ?string $audience,
    ) {
    }

    public function __invoke(Request $request): ?Authenticatable
    {
        $token = BearerToken::fromAuthorizationHeader($request->headers->get('Authorization'));
        if (null === $token) {
            return null;
        }
        try {
            $identity = $this->client->verifyAccessToken($token, $this->audience);
        } catch (InvalidTokenException $exception) {
            $this->logger->info('Rejected bearer token: ' . $exception->getMessage());

            return null;
        } catch (HttpException $exception) {
            $this->logger->warning('Bearer token could not be verified: ' . $exception->getMessage());

            return null;
        }

        return $this->provisioner->provision($identity, $this->roleMapper->map($identity));
    }
}
