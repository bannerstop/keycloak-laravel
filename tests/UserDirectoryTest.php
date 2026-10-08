<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakLaravel\Tests;

use Bannerstop\Keycloak\Admin\UserDirectory;
use Bannerstop\Keycloak\Exception\ConfigurationException;
use Bannerstop\Keycloak\Exception\HttpException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs against the Keycloak of the core package's tests-e2e (KEYCLOAK_URL).
 */
#[Group('keycloak')]
final class UserDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (false === getenv('KEYCLOAK_URL')) {
            self::markTestSkipped('Set KEYCLOAK_URL to run the tests against a real Keycloak.');
        }
        parent::setUp();
    }

    public function testUsesTheLoginClientByDefault(): void
    {
        self::assertContains('jdoe', $this->usernames());
    }

    public function testUsesASeparateClientWhenConfigured(): void
    {
        $this->app['config']->set('keycloak.directory.client_id', 'app-directory');
        $this->app['config']->set('keycloak.directory.client_secret', 'app-directory-secret');

        self::assertContains('jdoe', $this->usernames());
    }

    public function testTheSeparateClientReallyAuthenticatesItself(): void
    {
        $this->app['config']->set('keycloak.directory.client_id', 'app-directory');
        $this->app['config']->set('keycloak.directory.client_secret', 'wrong-secret');

        $this->expectException(HttpException::class);

        $this->usernames();
    }

    public function testASeparateClientNeedsASecret(): void
    {
        $this->app['config']->set('keycloak.directory.client_id', 'app-directory');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('KEYCLOAK_DIRECTORY_CLIENT_SECRET');

        $this->app->make(UserDirectory::class);
    }

    /**
     * @return string[]
     */
    private function usernames(): array
    {
        $usernames = [];
        foreach ($this->app->make(UserDirectory::class)->users() as $user) {
            $usernames[] = $user->getUsername();
        }

        return $usernames;
    }
}
