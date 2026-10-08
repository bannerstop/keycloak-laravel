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
| 6.x     | ≥ 8.1   | 9.x – 10.x  |
| 7.x     | ≥ 8.2   | 10.x – 12.x |
| 8.x     | ≥ 8.3   | 11.x – 13.x |
| 9.x     | ≥ 8.4   | 12.x – 13.x |

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

### Inertia

The package works with [Inertia](https://inertiajs.com) without extra setup.
Inertia visits are XHR requests, which cannot follow a redirect to Keycloak;
for them, the login and logout routes answer with `409` and
`X-Inertia-Location`, so Inertia sends the browser to Keycloak itself. A
`<Link href="/keycloak/login">` and `router.post('/keycloak/logout')` therefore
work like plain links and forms.

`KeycloakUser` is `Arrayable` and `JsonSerializable`, so it can go straight
into the shared props:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [...parent::share($request), 'auth' => ['user' => $request->user()]];
}
```

The frontend then gets `subject`, `email`, `name` and `roles`.

### Ending sessions with Keycloak

Logging out of Keycloak, or of another application, does not end the Laravel
session by itself. Two mechanisms close that gap; use both.

**Back-channel logout**: Keycloak posts a signed logout token to
`POST /keycloak/backchannel-logout` (route `keycloak.backchannel-logout`,
without the `web` group, so no session and no CSRF token) whenever a session
ends. In the Keycloak client, set *Backchannel logout URL* to
`https://your-app.example/keycloak/backchannel-logout` and turn on
*Backchannel logout session required*. Ended sessions are remembered in the
cache store `keycloak.cache_store` for `keycloak.session.revocation_ttl`
seconds (default 8 h, should be at least the session lifetime); with several
web servers the store must be shared, e.g. Redis or the database.

**Session check**: the `keycloak.session` middleware ends sessions that
Keycloak revoked through the back channel. With
`KEYCLOAK_SESSION_CHECK_INTERVAL` (seconds) it also redeems the refresh token
that often, which fails once the Keycloak session is gone (logout elsewhere,
user disabled, SSO session expired). If Keycloak is unreachable, the session is
kept. Keycloak does not send a back-channel call for every session in every
case (e.g. when an administrator signs a user out of all sessions), so keep the
interval check on as a safety net, e.g. 300.

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: ['keycloak.session']);
})
```

```dotenv
KEYCLOAK_SESSION_CHECK_INTERVAL=300
```

When a session ends, the middleware logs the user out and answers with a
redirect to the same URL (the `auth` middleware then sends them to the login),
`401` for JSON requests, or `409` + `X-Inertia-Location` for Inertia visits.
Livewire (e.g. Filament) requests use `fetch()` too, which cannot follow a
redirect to Keycloak; send them to a page on your own host instead. Sessions
that were not opened by a Keycloak login are left alone. Parallel requests of
one session may both run the refresh check; that only costs a second token
request.

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
