# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Laravel versions.

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
