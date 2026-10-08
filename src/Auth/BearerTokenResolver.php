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
    /** @var KeycloakClient */
    private $client;

    /** @var RoleMapper */
    private $roleMapper;

    /** @var UserProvisioner */
    private $provisioner;

    /** @var LoggerInterface */
    private $logger;

    /** @var string|null */
    private $audience;

    public function __construct(KeycloakClient $client, RoleMapper $roleMapper, UserProvisioner $provisioner, LoggerInterface $logger, ?string $audience)
    {
        $this->client = $client;
        $this->roleMapper = $roleMapper;
        $this->provisioner = $provisioner;
        $this->logger = $logger;
        $this->audience = $audience;
    }

    public function __invoke(Request $request): ?Authenticatable
    {
        $token = BearerToken::fromAuthorizationHeader($request->header('Authorization'));
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
