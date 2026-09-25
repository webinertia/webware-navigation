# webware-navigation

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-navigation)](https://packagist.org/packages/webware/webware-navigation)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-navigation)](https://packagist.org/packages/webware/webware-navigation)
[![License](https://img.shields.io/github/license/webinertia/webware-navigation)](LICENSE)
[![Continuous Integration](https://github.com/webinertia/webware-navigation/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/webware-navigation/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-navigation/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-navigation)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-navigation%2F0.1.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-navigation/0.1.x)

ACL-aware navigation trees for Mezzio, driven by `Route::setOptions()`.

## Documentation

| Document | Contents |
|---|---|
| [Overview](docs/overview.md) | Goals, quick start, route options reference |
| [Architecture](docs/architecture.md) | Design decisions and rationale |
| [Component Reference](docs/component-reference.md) | API docs for every class |
| [Extending](docs/extending.md) | Custom renderers, planned RendererPluginManager |
| [Testing](docs/testing.md) | Unit test examples and guidance |

## Installation

Private package — not published to Packagist. Consume via a VCS repository entry:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/webinertia/webware-navigation" }
    ],
    "require": {
        "webware/webware-navigation": "0.1.x-dev"
    }
}
```

After each change run `composer update webware/webware-navigation` (the lock
pins the commit hash).

Note: the package types against `Webware\Acl\AclInterface` and
`Webware\UserManager\UserInterface`, which the consuming application provides
until `webware-acl` + `webware-usermanager` publish — see
`docs/webware-tools-alignment.md`.

Add to `config/config.php`:

```php
Webware\Navigation\ConfigProvider::class,
```

That registers the middleware factory and the `navigation` view helper. The
middleware itself is **not** piped for you: add it to `config/pipeline.php`
after `RouteMiddleware` and before `DispatchMiddleware`, so that `RouteResult`
is on the request by the time it runs:

```php
$app->pipe(RouteMiddleware::class);
// ...
$app->pipe(UrlHelperMiddleware::class);
$app->pipe(NavigationMiddleware::class);
$app->pipe(DispatchMiddleware::class);
```

The package deliberately does not self-register through Mezzio's
`middleware_pipeline` config. That mechanism orders middleware by an integer
priority, and this middleware's correctness depends on running after
`RouteMiddleware` and before `DispatchMiddleware`, an ordering such a priority
cannot express safely. See [Architecture](docs/architecture.md), Decision 7.

## Namespace

```
Package:   webware/navigation
Namespace: Webware\Navigation\
```
