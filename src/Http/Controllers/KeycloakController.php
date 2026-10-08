<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Http\Controllers;

use Bannerstop\Keycloak\Exception\HttpException;
use Bannerstop\Keycloak\Exception\LoginException;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Login\RedirectTarget;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\Keycloak\Token\TokenSet;
use Bannerstop\KeycloakLaravel\Auth\UserProvisioner;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class KeycloakController extends Controller
{
    private const string TOKENS = 'keycloak.tokens';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly LoginFlow $flow,
        private readonly KeycloakClient $client,
        private readonly RoleMapper $roleMapper,
        private readonly UserProvisioner $provisioner,
        private readonly AuthFactory $auth,
        private readonly Redirector $redirector,
        private readonly LoggerInterface $logger,
        Config $config,
    ) {
        $this->config = (array) $config->get('keycloak.login');
    }

    /**
     * Starts the login. Pass "return_to" (a local path) to come back to a page afterwards.
     */
    public function login(Request $request): Response
    {
        $returnTo = $request->query->get('return_to');

        return $this->away($request, $this->flow->start(
            $this->redirector->getUrlGenerator()->route('keycloak.callback'),
            is_string($returnTo) && RedirectTarget::isLocal($returnTo) ? $returnTo : null,
            (array) ($this->config['authorization_parameters'] ?? [])
        ));
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $result = $this->flow->finish($request->query->all());
        } catch (LoginException $exception) {
            $this->logger->notice('Keycloak login failed: ' . $exception->getMessage(), ['reason' => $exception->getReason()->value]);

            return $this->redirector->to((string) $this->config['failure_redirect_to'])->with('keycloak_error', $exception->getReason()->value);
        }

        $identity = $result->getIdentity();
        $user = $this->provisioner->provision($identity, $this->roleMapper->map($identity));
        $this->guard()->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::TOKENS, $result->getTokens()->toArray());

        $returnTo = $result->getReturnTo();
        if (null !== $returnTo && RedirectTarget::isLocal($returnTo)) {
            return $this->redirector->to($returnTo);
        }

        return $this->redirector->intended((string) $this->config['redirect_to']);
    }

    /**
     * Ends the local and the Keycloak session.
     */
    public function logout(Request $request): Response
    {
        $tokens = $request->session()->get(self::TOKENS);
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $target = $this->redirector->getUrlGenerator()->to((string) $this->config['logout_redirect_to']);
        if (!is_array($tokens)) {
            return $this->redirector->to($target);
        }
        try {
            $url = $this->client->getLogoutUrl($target, TokenSet::fromArray($tokens)->getIdToken());
        } catch (HttpException) {
            $url = null;
        }

        return null === $url ? $this->redirector->to($target) : $this->away($request, $url);
    }

    /**
     * Inertia visits are XHR requests, which cannot follow a redirect to
     * Keycloak's host. Inertia expects a 409 with X-Inertia-Location instead
     * and then navigates the browser itself (what Inertia::location() does).
     */
    private function away(Request $request, string $url): Response
    {
        if ($request->headers->has('X-Inertia')) {
            return new Response('', Response::HTTP_CONFLICT, ['X-Inertia-Location' => $url]);
        }

        return $this->redirector->away($url);
    }

    /**
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    private function guard()
    {
        return $this->auth->guard($this->config['guard'] ?? null);
    }
}
