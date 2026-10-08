<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Tests;

use Bannerstop\KeycloakLaravel\KeycloakServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    /** @var array<string, string> */
    private array $cookies = [];

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [KeycloakServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('keycloak.server_url', getenv('KEYCLOAK_URL') ?: 'http://keycloak:8080');
        $app['config']->set('keycloak.realm', 'example');
        $app['config']->set('keycloak.client_id', 'app');
        $app['config']->set('keycloak.client_secret', 'app-secret');
        $app['config']->set('keycloak.routes.prefix', 'login/keycloak');
        $app['config']->set('keycloak.login.allowed_email_domains', ['example.com']);
        $app['config']->set('keycloak.login.failure_redirect_to', '/public');
        $app['config']->set('keycloak.roles.realm_roles', ['admin' => ['admin']]);
        $app['config']->set('keycloak.roles.client_roles', ['app' => ['editor' => ['editor']]]);
        $app['config']->set('keycloak.roles.groups', ['/staff/it' => 'it']);
        $app['config']->set('keycloak.bearer.audience', 'api');

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.providers.keycloak', ['driver' => 'keycloak']);
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'keycloak']);
        $app['config']->set('auth.guards.api', ['driver' => 'keycloak-bearer']);
    }

    protected function defineTestRoutes(): void
    {
        $me = static function (Request $request) {
            $user = $request->user();

            return null === $user ? ['user' => null] : $user->toArray();
        };
        Route::middleware('web')->group(function () use ($me) {
            Route::get('/login', static fn () => redirect()->route('keycloak.login'))->name('login');
            Route::get('/me', $me)->middleware('auth');
            // what an Inertia app shares with every page
            Route::get('/shared', static fn (Request $request): array => ['auth' => ['user' => $request->user()]])->middleware('auth');
            Route::get('/admin', $me)->middleware(['auth', 'keycloak.role:admin']);
            Route::get('/checked', $me)->middleware(['keycloak.session', 'auth']);
            Route::get('/public', static fn () => ['error' => session('keycloak_error')]);
        });
        Route::get('/api/me', $me)->middleware('auth:api');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineTestRoutes();
    }

    /**
     * A request that keeps cookies like a browser.
     *
     * @param array<string, string> $server
     *
     * @return \Illuminate\Testing\TestResponse|\Illuminate\Foundation\Testing\TestResponse
     */
    protected function browse(string $method, string $uri, array $server = [])
    {
        // Every real request starts with fresh guards; in tests the application lives on.
        (function (): void {
            $this->guards = [];
        })->call($this->app['auth']);
        $response = $this->call($method, $uri, [], $this->cookies, [], $server);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    /**
     * @return \Illuminate\Testing\TestResponse|\Illuminate\Foundation\Testing\TestResponse
     */
    protected function login()
    {
        $authorizationUrl = (string) $this->browse('GET', '/login/keycloak/login')->headers->get('Location');

        return $this->browse('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));
    }

    /**
     * Fills in the Keycloak login form like a browser and returns the callback query.
     *
     * @return array<string, string>
     */
    protected static function keycloakLogin(string $authorizationUrl): array
    {
        $cookies = (string) tempnam(sys_get_temp_dir(), 'kc');
        $curl = curl_init($authorizationUrl);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
        $html = (string) curl_exec($curl);
        self::assertSame(1, preg_match('/<form[^>]+id="kc-form-login"[^>]+action="([^"]+)"/', $html, $form), 'Keycloak shows its login form.');
        curl_setopt_array($curl, [
            CURLOPT_URL => html_entity_decode($form[1]),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['username' => 'jdoe', 'password' => 'jane-password']),
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($curl);
        curl_close($curl);
        self::assertSame(1, preg_match('/^Location: (\S+)/mi', $response, $location), 'Keycloak redirects back.');
        parse_str((string) parse_url($location[1], PHP_URL_QUERY), $query);

        return $query;
    }
}
