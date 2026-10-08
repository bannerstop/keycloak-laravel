# bannerstop/keycloak-laravel

Laravel integration of [bannerstop/keycloak](https://github.com/bannerstop/keycloak):
single sign-on with Keycloak.

- Login and logout routes that use Laravel's own session guard
- A `keycloak-bearer` guard for APIs
- A `keycloak.role` middleware
- Role mapping from realm roles, client roles and groups
- Works without a user table (session-only `KeycloakUser`) or with your own models (`UserProvisioner`)

## Versions

| Version | PHP     | Laravel     |
|---------|---------|-------------|
| 1.x     | ≥ 7.1.3 | 5.8 – 8.x   |
| 2.x     | ≥ 7.2   | 6.x – 8.x   |
| 3.x     | ≥ 7.3   | 6.x – 8.x   |
| 4.x     | ≥ 7.4   | 6.x – 8.x   |
| 5.x     | ≥ 8.0   | 8.x – 9.x   |

## Installation

```bash
composer require bannerstop/keycloak-laravel
```

The package talks to Keycloak through Guzzle 7. Set `keycloak.http_client`
to the class name of another PSR-18 client to replace it.

Publish the configuration and set the environment variables:

```bash
php artisan vendor:publish --tag=keycloak-config
```

```dotenv
KEYCLOAK_SERVER_URL=https://sso.example.com
KEYCLOAK_REALM=example
KEYCLOAK_CLIENT_ID=my-app
KEYCLOAK_CLIENT_SECRET=
```

In Keycloak, register `https://your-app.example/keycloak/callback` as redirect
URI and your logout target as post logout redirect URI. See the
[core README](https://github.com/bannerstop/keycloak#keycloak-setup) for the
full client setup.

## Authentication

### Users without a user table

```php
// config/auth.php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'keycloak'],
    'api' => ['driver' => 'keycloak-bearer'],
],
'providers' => [
    'keycloak' => ['driver' => 'keycloak'],
],
```

`auth()->user()` is then a `KeycloakUser` with `getSubject()`, `getEmail()`,
`getName()` and `getKeycloakRoles()`.

### Your own users

Implement `UserProvisioner` and set `keycloak.user_provisioner` to the class.
Keep the guard's normal provider (e.g. `eloquent`), so that Laravel can load the
user on the following requests:

```php
use Bannerstop\Keycloak\Identity;
use Bannerstop\KeycloakLaravel\Auth\UserProvisioner;
use Illuminate\Contracts\Auth\Authenticatable;

final class KeycloakUserProvisioner implements UserProvisioner
{
    public function provision(Identity $identity, array $roles): Authenticatable
    {
        return User::updateOrCreate(
            ['keycloak_id' => $identity->getSubject()],
            ['email' => $identity->getEmail(), 'name' => $identity->getDisplayName(), 'roles' => $roles]
        );
    }
}
```

Let your model implement `HasKeycloakRoles` to use the `keycloak.role` middleware.

### Routes

| Route | Name | |
|-------|------|-|
| `GET /keycloak/login?return_to=/path` | `keycloak.login` | starts the login |
| `GET /keycloak/callback` | `keycloak.callback` | the redirect URI |
| `POST /keycloak/logout` | `keycloak.logout` | ends the local and the Keycloak session |

Point Laravel's `login` route to `keycloak.login`, e.g.
`Route::redirect('/login', '/keycloak/login')->name('login');`. After the login
the user goes to `return_to` (local paths only), the intended URL or
`keycloak.login.redirect_to`. Failed logins go to `keycloak.login.failure_redirect_to`
with the reason flashed as `keycloak_error` (`state_mismatch`, `cancelled`,
`provider_error`, `invalid_token`, `not_allowed`).

Logout is POST only, so that other sites cannot log your users out:

```blade
<form method="POST" action="{{ route('keycloak.logout') }}">
    @csrf
    <button>Log out</button>
</form>
```

### Roles

```php
// config/keycloak.php
'roles' => [
    'default_roles' => ['user'],
    'realm_roles' => ['admin' => ['admin']],
    'client_roles' => ['my-app' => ['editor' => ['editor']]],
    'groups' => ['/staff/it' => ['it']],
],
```

```php
Route::get('/admin', AdminController::class)->middleware(['auth', 'keycloak.role:admin']);
```

### APIs

```php
Route::middleware('auth:api')->get('/api/orders', ...);
```

Requests need `Authorization: Bearer <access token>`; the token must carry the
audience from `keycloak.bearer.audience` (default: the client id). Add an
audience mapper in Keycloak for that.

### User directory

`Bannerstop\Keycloak\Admin\UserDirectory` lists the users of the realm through
the admin REST API, e.g. to sync a user table. It authenticates with a service
account that needs the client role `realm-management` → `view-users`.

Without further configuration it uses the login client, which then needs
*Service accounts roles* and `view-users` itself. We recommend a separate
confidential client that only has *Service accounts roles* enabled and
`view-users` assigned, so that the login client has no admin API rights:

```dotenv
KEYCLOAK_DIRECTORY_CLIENT_ID=my-app-directory
KEYCLOAK_DIRECTORY_CLIENT_SECRET=
```

```php
use Bannerstop\Keycloak\Admin\UserDirectory;

foreach (app(UserDirectory::class)->users() as $user) {
    // $user->getId() equals the "sub" of the user's tokens
}
```

### Services

`Bannerstop\Keycloak\KeycloakClient`, `Bannerstop\Keycloak\Admin\UserDirectory`
and `Bannerstop\Keycloak\Role\RoleMapper` are bound in the container.

## License

MIT, see [LICENSE](LICENSE). Security issues: see the
[core package's security policy](https://github.com/bannerstop/keycloak/blob/main/SECURITY.md).
