<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Tests;

use Bannerstop\Keycloak\Role\RoleMapper;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs against the Keycloak of the core package's tests-e2e (KEYCLOAK_URL).
 */
#[Group('keycloak')]
final class KeycloakLoginTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (false === getenv('KEYCLOAK_URL')) {
            self::markTestSkipped('Set KEYCLOAK_URL to run the tests against a real Keycloak.');
        }
        parent::setUp();
    }

    public function testBrowserLoginAndLogout(): void
    {
        $response = $this->browse('GET', '/me');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://localhost/login', $response->headers->get('Location'));

        $authorizationUrl = (string) $this->browse('GET', '/login/keycloak/login?return_to=/me')->headers->get('Location');
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/auth?', $authorizationUrl);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        self::assertSame('http://localhost/login/keycloak/callback', $query['redirect_uri']);

        $response = $this->browse('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));
        self::assertSame('http://localhost/me', $response->headers->get('Location'));

        $me = $this->browse('GET', '/me')->json();
        self::assertSame('jane.doe@example.com', $me['email']);
        self::assertSame('Jane Doe', $me['name']);
        self::assertSame(['user', 'admin', 'editor', 'it'], $me['roles']);
        self::assertSame(200, $this->browse('GET', '/admin')->getStatusCode());

        $response = $this->browse('POST', '/login/keycloak/logout');
        $logout = (string) $response->headers->get('Location');
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/logout?', $logout);
        parse_str((string) parse_url($logout, PHP_URL_QUERY), $query);
        self::assertSame('http://localhost', rtrim($query['post_logout_redirect_uri'], '/'));
        self::assertArrayHasKey('id_token_hint', $query);

        self::assertSame(302, $this->browse('GET', '/me')->getStatusCode(), 'The local session is gone.');
    }

    public function testInertiaVisits(): void
    {
        $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

        // An XHR cannot follow a redirect to Keycloak: Inertia gets 409 + X-Inertia-Location and navigates itself.
        $response = $this->browse('GET', '/login/keycloak/login?return_to=/me', $inertia);
        self::assertSame(409, $response->getStatusCode());
        $authorizationUrl = (string) $response->headers->get('X-Inertia-Location');
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/auth?', $authorizationUrl);

        $this->browse('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));

        $shared = $this->browse('GET', '/shared', $inertia)->json();
        self::assertSame('jane.doe@example.com', $shared['auth']['user']['email']);
        self::assertSame(['user', 'admin', 'editor', 'it'], $shared['auth']['user']['roles']);

        $response = $this->browse('POST', '/login/keycloak/logout', $inertia);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/logout?', (string) $response->headers->get('X-Inertia-Location'));
        self::assertSame(302, $this->browse('GET', '/me')->getStatusCode(), 'The local session is gone.');
    }

    public function testRolesAreOnlyGrantedWhenMapped(): void
    {
        $this->app['config']->set('keycloak.roles.realm_roles', []);
        $this->app->forgetInstance(RoleMapper::class);

        $this->login();

        self::assertSame(403, $this->browse('GET', '/admin')->getStatusCode());
    }

    public function testExternalReturnPathsAreIgnored(): void
    {
        $authorizationUrl = (string) $this->browse('GET', '/login/keycloak/login?return_to=' . rawurlencode('//evil.example/'))->headers->get('Location');
        $response = $this->browse('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));

        self::assertSame('http://localhost', rtrim((string) $response->headers->get('Location'), '/'));
    }

    public function testFailedLoginsEndOnTheFailurePath(): void
    {
        $this->app['config']->set('keycloak.login.allowed_email_domains', ['example.org']);

        $response = $this->login();
        self::assertSame('http://localhost/public', $response->headers->get('Location'));
        self::assertSame(['error' => 'not_allowed'], $this->browse('GET', '/public')->json());
        self::assertSame(302, $this->browse('GET', '/me')->getStatusCode());
    }

    public function testForgedCallbacksAreRejected(): void
    {
        $this->browse('GET', '/login/keycloak/callback?state=forged&code=forged');

        self::assertSame(['error' => 'state_mismatch'], $this->browse('GET', '/public')->json());
    }

    public function testBearerTokens(): void
    {
        $response = $this->browse('GET', '/api/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . self::passwordGrantAccessToken()]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('jane.doe@example.com', $response->json()['email']);

        $response = $this->browse('GET', '/api/me', ['HTTP_AUTHORIZATION' => 'Bearer a.b.c', 'HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return \Illuminate\Testing\TestResponse|\Illuminate\Foundation\Testing\TestResponse
     */
    private function login()
    {
        $authorizationUrl = (string) $this->browse('GET', '/login/keycloak/login')->headers->get('Location');

        return $this->browse('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));
    }

    /**
     * Fills in the Keycloak login form like a browser and returns the callback query.
     *
     * @return array<string, string>
     */
    private static function keycloakLogin(string $authorizationUrl): array
    {
        $cookies = (string) tempnam(sys_get_temp_dir(), 'kc');
        $curl = curl_init($authorizationUrl);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
        $form = \Dom\HTMLDocument::createFromString((string) curl_exec($curl), LIBXML_NOERROR)->getElementById('kc-form-login');
        self::assertNotNull($form, 'Keycloak shows its login form.');
        curl_setopt_array($curl, [
            CURLOPT_URL => $form->getAttribute('action'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['username' => 'jdoe', 'password' => 'jane-password']),
        ]);
        curl_exec($curl);
        $location = (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        self::assertNotSame('', $location, 'Keycloak redirects back.');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private static function passwordGrantAccessToken(): string
    {
        $curl = curl_init(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/token');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'password', 'client_id' => 'app', 'client_secret' => 'app-secret', 'username' => 'jdoe', 'password' => 'jane-password', 'scope' => 'openid']),
        ]);
        $response = json_decode((string) curl_exec($curl), true);

        return $response['access_token'];
    }
}
