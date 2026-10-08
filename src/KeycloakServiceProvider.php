<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel;

use Bannerstop\Keycloak\Admin\UserDirectory;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\KeycloakConfig;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Policy\EmailDomainPolicy;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\KeycloakLaravel\Auth\BearerTokenResolver;
use Bannerstop\KeycloakLaravel\Auth\KeycloakUserProvider;
use Bannerstop\KeycloakLaravel\Auth\SessionStateStore;
use Bannerstop\KeycloakLaravel\Auth\UserProvisioner;
use Bannerstop\KeycloakLaravel\Http\Middleware\RequireKeycloakRole;
use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client as Guzzle6Adapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

final class KeycloakServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/keycloak.php', 'keycloak');

        $this->app->singleton(KeycloakConfig::class, fn (Application $app): KeycloakConfig => KeycloakConfig::fromArray((array) $app->make('config')->get('keycloak')));
        $this->app->singleton(KeycloakClient::class, function (Application $app): KeycloakClient {
            $factory = new Psr17Factory();

            return new KeycloakClient(
                $app->make(KeycloakConfig::class),
                $this->httpClient($app),
                $factory,
                $factory,
                $app->make('cache')->store($app->make('config')->get('keycloak.cache_store'))
            );
        });
        $this->app->singleton(UserDirectory::class, fn (Application $app): UserDirectory => new UserDirectory($app->make(KeycloakClient::class)));
        $this->app->singleton(RoleMapper::class, fn (Application $app): RoleMapper => RoleMapper::fromArray((array) $app->make('config')->get('keycloak.roles')));
        $this->app->bind(LoginFlow::class, function (Application $app): LoginFlow {
            $login = (array) $app->make('config')->get('keycloak.login');
            $policies = [];
            if ([] !== (array) ($login['allowed_email_domains'] ?? [])) {
                $policies[] = new EmailDomainPolicy((array) $login['allowed_email_domains'], (bool) ($login['require_verified_email'] ?? true));
            }

            return new LoginFlow($app->make(KeycloakClient::class), new SessionStateStore($app->make('session.store')), $policies);
        });
        $this->app->bind(KeycloakUserProvider::class, fn (Application $app): KeycloakUserProvider => new KeycloakUserProvider($app->make('session.store')));
        $this->app->bind(UserProvisioner::class, fn (Application $app): UserProvisioner => $app->make($app->make('config')->get('keycloak.user_provisioner') ?? KeycloakUserProvider::class));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/keycloak.php' => $this->app->configPath('keycloak.php')], 'keycloak-config');

        if ($this->app->make('config')->get('keycloak.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/keycloak.php');
        }

        Auth::provider('keycloak', fn (Application $app): KeycloakUserProvider => $app->make(KeycloakUserProvider::class));
        Auth::viaRequest('keycloak-bearer', fn ($request) => (new BearerTokenResolver(
            $this->app->make(KeycloakClient::class),
            $this->app->make(RoleMapper::class),
            $this->app->make(UserProvisioner::class),
            $this->app->make('log'),
            $this->app->make('config')->get('keycloak.bearer.audience')
        ))($request));

        $this->app->make('router')->aliasMiddleware('keycloak.role', RequireKeycloakRole::class);
    }

    private function httpClient(Application $app): ClientInterface
    {
        $configured = $app->make('config')->get('keycloak.http_client');
        if (null !== $configured) {
            return $app->make($configured);
        }
        if (class_exists(GuzzleClient::class) && is_subclass_of(GuzzleClient::class, ClientInterface::class)) {
            return new GuzzleClient(['timeout' => 10]);
        }
        if (class_exists(Guzzle6Adapter::class)) {
            return Guzzle6Adapter::createWithConfig(['timeout' => 10]);
        }

        throw new \LogicException('bannerstop/keycloak-laravel needs a PSR-18 HTTP client: install guzzlehttp/guzzle ^7, php-http/guzzle6-adapter, or set keycloak.http_client.');
    }
}
