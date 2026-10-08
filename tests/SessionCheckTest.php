<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Tests;

use Bannerstop\Keycloak\Session\KeycloakSession;
use Bannerstop\Keycloak\Session\SessionRevocations;
use Bannerstop\Keycloak\Token\LogoutToken;
use Bannerstop\KeycloakLaravel\Http\Controllers\KeycloakController;

/**
 * Back-channel logout and the "keycloak.session" middleware, against the
 * Keycloak of the core package's tests-e2e (KEYCLOAK_URL).
 *
 * @group keycloak
 */
final class SessionCheckTest extends TestCase
{
    protected function setUp(): void
    {
        if (false === getenv('KEYCLOAK_URL')) {
            self::markTestSkipped('Set KEYCLOAK_URL to run the tests against a real Keycloak.');
        }
        parent::setUp();
    }

    public function testBackchannelLogoutRejectsInvalidTokensWithoutASession(): void
    {
        $response = $this->call('POST', '/login/keycloak/backchannel-logout', ['logout_token' => 'a.b.c']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $response->headers->getCookies(), 'No session is started for Keycloak\'s call.');
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        self::assertSame(400, $this->call('POST', '/login/keycloak/backchannel-logout')->getStatusCode());
    }

    public function testARevokedKeycloakSessionEndsTheSession(): void
    {
        $this->login();
        self::assertSame(200, $this->browse('GET', '/checked')->getStatusCode());

        $this->revoke($this->keycloakSession());

        $response = $this->browse('GET', '/checked?tab=1');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://localhost/checked?tab=1', $response->headers->get('Location'));
        self::assertSame('http://localhost/login', $this->browse('GET', '/checked?tab=1')->headers->get('Location'), 'The user is logged out.');
    }

    public function testInertiaVisitsReloadThePage(): void
    {
        $this->login();
        $this->revoke($this->keycloakSession());

        $response = $this->browse('GET', '/checked', ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('http://localhost/checked', $response->headers->get('X-Inertia-Location'));
    }

    public function testJsonRequestsGetA401(): void
    {
        $this->login();
        $this->revoke($this->keycloakSession());

        self::assertSame(401, $this->browse('GET', '/checked', ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
    }

    public function testTheRefreshCheckEndsASessionKeycloakEnded(): void
    {
        $this->app['config']->set('keycloak.session.check_interval', 1);
        $this->login();
        self::adminApi('DELETE', '/sessions/' . $this->keycloakSession()->getSessionId());
        sleep(2);

        $response = $this->browse('GET', '/checked');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://localhost/login', $this->browse('GET', '/checked')->headers->get('Location'));
    }

    public function testTheRefreshCheckKeepsALiveSessionAndRenewsItsTokens(): void
    {
        $this->app['config']->set('keycloak.session.check_interval', 1);
        $this->login();
        $before = $this->keycloakSession();
        sleep(2);

        self::assertSame(200, $this->browse('GET', '/checked')->getStatusCode());

        $after = $this->keycloakSession();
        self::assertGreaterThan($before->getCheckedAt(), $after->getCheckedAt());
        self::assertNotSame($before->getTokens()->getAccessToken(), $after->getTokens()->getAccessToken());
        self::assertSame($before->getSessionId(), $after->getSessionId());
    }

    private function keycloakSession(): KeycloakSession
    {
        $session = KeycloakSession::fromArray((array) $this->app['session.store']->get(KeycloakController::SESSION, []));
        self::assertNotNull($session, 'The login stored its Keycloak session.');

        return $session;
    }

    /**
     * What the back-channel logout records when Keycloak ends this session.
     */
    private function revoke(KeycloakSession $session): void
    {
        $this->app->make(SessionRevocations::class)->revoke(new LogoutToken($session->getSubject(), $session->getSessionId(), time(), bin2hex(random_bytes(8))));
    }

    private static function adminApi(string $method, string $path): void
    {
        $curl = curl_init(getenv('KEYCLOAK_URL') . '/realms/master/protocol/openid-connect/token');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'password', 'client_id' => 'admin-cli', 'username' => 'admin', 'password' => 'admin'])]);
        $token = json_decode((string) curl_exec($curl), true)['access_token'];
        $curl = curl_init(getenv('KEYCLOAK_URL') . '/admin/realms/example' . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
        curl_exec($curl);
        self::assertLessThan(300, curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $method . ' ' . $path);
    }
}
