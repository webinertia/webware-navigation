# webware-navigation

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

The middleware is self-registering via `ConfigProvider`. No manual pipeline edit
is required beyond the entry in `config/config.php`.

## Namespace

```
Package:   webware/navigation
Namespace: Webware\Navigation\
```
