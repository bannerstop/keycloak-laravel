# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Laravel versions.

## 10.0.0

- Requires PHP 8.5 or later, Laravel 12 or 13 and bannerstop/keycloak 10.x.
- No API changes beyond the PHP range. The test suite reads the Keycloak login
  form with `Dom\HTMLDocument` and drops the `curl_close()` calls deprecated in
  PHP 8.5.

## 9.0.0

- Requires PHP 8.4 or later, Laravel 12 or 13 and bannerstop/keycloak 9.x.
- `keycloak.role` checks roles with `array_any()`; `new` without parentheses.
- The test suite fails on deprecations triggered by the package itself.

## 8.0.0

- Requires PHP 8.3 or later, Laravel 11, 12 or 13 and bannerstop/keycloak 8.x.
- Typed class constants and `#[\Override]` on every implemented contract method.

## 7.0.0

- Requires PHP 8.2 or later, Laravel 10, 11 or 12 and bannerstop/keycloak 7.x.
- Readonly classes; credentials and remember tokens passed to the user provider
  are marked `#[\SensitiveParameter]` and stay out of stack traces.

## 6.0.0

- Requires PHP 8.1 or later, Laravel 9 or 10 and bannerstop/keycloak 6.x.
- The flashed `keycloak_error` keeps its string values; they now come from
  the core's `LoginFailure` enum.
- Readonly properties throughout.

## 5.0.0

- Requires PHP 8.0 or later, Laravel 8 or 9 and bannerstop/keycloak 5.x.
- Talks to Keycloak through Guzzle 7, now a regular dependency; the Guzzle 6
  adapter path is gone. `keycloak.http_client` still takes another PSR-18 client.
- Constructor property promotion, `mixed` and trailing commas throughout.

## 4.0.0

- Requires PHP 7.4 or later and bannerstop/keycloak 4.x.
- Typed properties and arrow functions throughout.

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
