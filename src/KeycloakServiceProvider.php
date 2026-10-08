<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel;

use Bannerstop\Keycloak\Admin\UserDirectory;
use Bannerstop\Keycloak\Exception\ConfigurationException;
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

        $this->app->singleton(KeycloakConfig::class, function (Application $app): KeycloakConfig {
            return KeycloakConfig::fromArray((array) $app->make('config')->get('keycloak'));
        });
        $this->app->singleton(KeycloakClient::class, function (Application $app): KeycloakClient {
            return $this->client($app, $app->make(KeycloakConfig::class));
        });
        $this->app->singleton(UserDirectory::class, function (Application $app): UserDirectory {
            return new UserDirectory($this->directoryClient($app));
        });
        $this->app->singleton(RoleMapper::class, function (Application $app): RoleMapper {
            return RoleMapper::fromArray((array) $app->make('config')->get('keycloak.roles'));
        });
        $this->app->bind(LoginFlow::class, function (Application $app): LoginFlow {
            $login = (array) $app->make('config')->get('keycloak.login');
            $policies = [];
            if ([] !== (array) ($login['allowed_email_domains'] ?? [])) {
                $policies[] = new EmailDomainPolicy((array) $login['allowed_email_domains'], (bool) ($login['require_verified_email'] ?? true));
            }

            return new LoginFlow($app->make(KeycloakClient::class), new SessionStateStore($app->make('session.store')), $policies);
        });
        $this->app->bind(KeycloakUserProvider::class, function (Application $app): KeycloakUserProvider {
            return new KeycloakUserProvider($app->make('session.store'));
        });
        $this->app->bind(UserProvisioner::class, function (Application $app): UserProvisioner {
            return $app->make($app->make('config')->get('keycloak.user_provisioner') ?? KeycloakUserProvider::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/keycloak.php' => $this->app->configPath('keycloak.php')], 'keycloak-config');

        if ($this->app->make('config')->get('keycloak.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/keycloak.php');
        }

        Auth::provider('keycloak', function (Application $app): KeycloakUserProvider {
            return $app->make(KeycloakUserProvider::class);
        });
        Auth::viaRequest('keycloak-bearer', function ($request) {
            return (new BearerTokenResolver(
                $this->app->make(KeycloakClient::class),
                $this->app->make(RoleMapper::class),
                $this->app->make(UserProvisioner::class),
                $this->app->make('log'),
                $this->app->make('config')->get('keycloak.bearer.audience')
            ))($request);
        });

        $this->app->make('router')->aliasMiddleware('keycloak.role', RequireKeycloakRole::class);
    }

    private function client(Application $app, KeycloakConfig $config): KeycloakClient
    {
        $factory = new Psr17Factory();

        return new KeycloakClient(
            $config,
            $this->httpClient($app),
            $factory,
            $factory,
            $app->make('cache')->store($app->make('config')->get('keycloak.cache_store'))
        );
    }

    /**
     * The client for the admin API: a separate one when keycloak.directory.client_id
     * is set, otherwise the login client.
     */
    private function directoryClient(Application $app): KeycloakClient
    {
        $directory = (array) $app->make('config')->get('keycloak.directory');
        $clientId = (string) ($directory['client_id'] ?? '');
        if ('' === $clientId) {
            return $app->make(KeycloakClient::class);
        }
        $clientSecret = (string) ($directory['client_secret'] ?? '');
        if ('' === $clientSecret) {
            throw new ConfigurationException('keycloak.directory.client_secret (KEYCLOAK_DIRECTORY_CLIENT_SECRET) is required when keycloak.directory.client_id is set.');
        }
        $options = array_merge((array) $app->make('config')->get('keycloak'), ['client_id' => $clientId, 'client_secret' => $clientSecret]);

        return $this->client($app, KeycloakConfig::fromArray($options));
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
