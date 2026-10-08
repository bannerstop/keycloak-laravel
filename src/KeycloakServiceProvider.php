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
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

final class KeycloakServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/keycloak.php', 'keycloak');

        $this->app->singleton(KeycloakConfig::class, function (Container $app): KeycloakConfig {
            return KeycloakConfig::fromArray((array) $app['config']->get('keycloak'));
        });
        $this->app->singleton(KeycloakClient::class, function (Container $app): KeycloakClient {
            $factory = new Psr17Factory();

            return new KeycloakClient(
                $app->make(KeycloakConfig::class),
                $this->httpClient($app),
                $factory,
                $factory,
                $app['cache']->store($app['config']->get('keycloak.cache_store'))
            );
        });
        $this->app->singleton(UserDirectory::class, function (Container $app): UserDirectory {
            return new UserDirectory($app->make(KeycloakClient::class));
        });
        $this->app->singleton(RoleMapper::class, function (Container $app): RoleMapper {
            return RoleMapper::fromArray((array) $app['config']->get('keycloak.roles'));
        });
        $this->app->bind(LoginFlow::class, function (Container $app): LoginFlow {
            $login = (array) $app['config']->get('keycloak.login');
            $policies = [];
            if ([] !== (array) ($login['allowed_email_domains'] ?? [])) {
                $policies[] = new EmailDomainPolicy((array) $login['allowed_email_domains'], (bool) ($login['require_verified_email'] ?? true));
            }

            return new LoginFlow($app->make(KeycloakClient::class), new SessionStateStore($app['session.store']), $policies);
        });
        $this->app->bind(KeycloakUserProvider::class, function (Container $app): KeycloakUserProvider {
            return new KeycloakUserProvider($app['session.store']);
        });
        $this->app->bind(UserProvisioner::class, function (Container $app): UserProvisioner {
            return $app->make($app['config']->get('keycloak.user_provisioner') ?? KeycloakUserProvider::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/keycloak.php' => $this->app->configPath('keycloak.php')], 'keycloak-config');

        if ($this->app['config']->get('keycloak.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/keycloak.php');
        }

        Auth::provider('keycloak', function (Container $app): KeycloakUserProvider {
            return $app->make(KeycloakUserProvider::class);
        });
        Auth::viaRequest('keycloak-bearer', function ($request) {
            return (new BearerTokenResolver(
                $this->app->make(KeycloakClient::class),
                $this->app->make(RoleMapper::class),
                $this->app->make(UserProvisioner::class),
                $this->app->make('log'),
                $this->app['config']->get('keycloak.bearer.audience')
            ))($request);
        });

        $this->app['router']->aliasMiddleware('keycloak.role', RequireKeycloakRole::class);
    }

    private function httpClient(Container $app): ClientInterface
    {
        $configured = $app['config']->get('keycloak.http_client');
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
