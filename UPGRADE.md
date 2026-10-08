# Upgrade guide

Each major version raises the minimum PHP version and the supported Laravel
versions. Only the steps that need changes in your code are listed.

## 6.x → 7.x

- PHP 8.2 or later and Laravel 10 or later are required. No code changes needed.

## 5.x → 6.x

- PHP 8.1 or later and Laravel 9 or later are required.
- If you use bannerstop/keycloak directly, see its upgrade guide (enums for
  algorithms and login failures). Code that only uses this package needs no
  changes.

## 4.x → 5.x

- PHP 8.0 or later and Laravel 8 or later are required.
- Remove `php-http/guzzle6-adapter`; the package now requires Guzzle 7.

## 3.x → 4.x

- PHP 7.4 or later is required. No code changes needed.

## 2.x → 3.x

- PHP 7.3 or later is required. No code changes needed.

## 1.x → 2.x

- PHP 7.2 or later and Laravel 6 or later are required. No code changes needed.

