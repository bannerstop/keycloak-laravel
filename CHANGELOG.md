# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Laravel versions.

## 3.2.0

- Works with Inertia: for Inertia visits, the login and logout routes answer
  with `409` and `X-Inertia-Location` instead of a redirect to Keycloak, which
  an XHR request cannot follow. Other requests get the redirect as before.
- `KeycloakUser` is `Arrayable` and `JsonSerializable` (subject, e-mail, name,
  roles), e.g. for Inertia's shared props.

## 3.1.0

- Optional separate client for the user directory: set
  `keycloak.directory.client_id` and `keycloak.directory.client_secret`
  (`KEYCLOAK_DIRECTORY_CLIENT_ID`, `KEYCLOAK_DIRECTORY_CLIENT_SECRET`), so that
  the login client needs no admin API rights. Without them the login client is
  used as before.

## 3.0.0

- Requires PHP 7.3 or later and bannerstop/keycloak 3.x.

## 2.0.0

- Requires PHP 7.2 or later and Laravel 6, 7 or 8 (Laravel 5.8 is dropped).
- Requires bannerstop/keycloak 2.x.

## 1.0.0

First release, PHP 7.1.3 and later, Laravel 5.8 to 8.

- Login, callback and logout routes on top of the session guard
- `keycloak` user provider, `keycloak-bearer` guard, `keycloak.role` middleware
- Role mapping, e-mail domain policy, user provisioning
