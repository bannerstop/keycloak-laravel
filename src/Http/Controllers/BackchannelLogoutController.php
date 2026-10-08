<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Http\Controllers;

use Bannerstop\Keycloak\Exception\KeycloakException;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Session\SessionRevocations;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keycloak's back-channel logout call (OpenID Connect Back-Channel Logout
 * 1.0): records the ended Keycloak session, so that the "keycloak.session"
 * middleware ends the matching sessions on their next request.
 */
final class BackchannelLogoutController extends Controller
{
    private KeycloakClient $client;
    private SessionRevocations $revocations;
    private LoggerInterface $logger;

    public function __construct(KeycloakClient $client, SessionRevocations $revocations, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->revocations = $revocations;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        $logoutToken = $request->request->get('logout_token');
        try {
            $accepted = is_string($logoutToken) && $this->revocations->revoke($this->client->verifyLogoutToken($logoutToken));
        } catch (KeycloakException $exception) {
            $this->logger->warning('Keycloak back-channel logout rejected: ' . $exception->getMessage());
            $accepted = false;
        }

        return new Response('', $accepted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
    }
}
