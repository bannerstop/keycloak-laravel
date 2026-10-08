# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Laravel versions.

## 2.0.0

- Requires PHP 7.2 or later and Laravel 6, 7 or 8 (Laravel 5.8 is dropped).
- Requires bannerstop/keycloak 2.x.

## 1.0.0

First release, PHP 7.1.3 and later, Laravel 5.8 to 8.

- Login, callback and logout routes on top of the session guard
- `keycloak` user provider, `keycloak-bearer` guard, `keycloak.role` middleware
- Role mapping, e-mail domain policy, user provisioning
