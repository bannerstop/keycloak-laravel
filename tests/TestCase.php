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
            Route::get('/admin', $me)->middleware(['auth', 'keycloak.role:admin']);
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
}
