# webware-navigation — Overview

`webware-navigation` is a standalone Mezzio/Laminas view-helper package that provides
ACL-aware navigation trees driven entirely by **route options**. Routes declare their
own navigation membership at definition time; the package filters, sorts, and renders
the result without any separate navigation configuration file.

---

## Goals

| Goal | How it is met |
|---|---|
| Single source of truth for nav membership | `Route::setOptions(['navigation' => 'admin', ...])` lives beside the route definition |
| ACL enforcement before render time | `NavigationFilterIterator` calls `AclInterface::isAllowedByRouteName()` per route |
| Multiple representations from one filtered tree | `NavigationContainer` exposes `menu()`, `breadcrumbs()`, and `sitemap()` |
| Replaceable rendering | Every render method delegates to an optional `RendererInterface` before falling back to inline markup |
| Active-item highlighting | `NavigationMiddleware` injects the matched route name from `RouteResult` after routing completes |
| Framework integration via DI | Standard `ConfigProvider` pattern; one middleware pipe entry |

---

## Package layout

```
src/webware-navigation/
└── src/
    ├── ConfigProvider.php
    ├── NavigationItem.php
    ├── NavigationContainer.php
    ├── NavigationFilterIterator.php
    ├── Container/
    │   └── NavigationMiddlewareFactory.php
    ├── Middleware/
    │   └── NavigationMiddleware.php
    ├── Renderer/
    │   └── RendererInterface.php
    └── View/
        └── Helper/
            ├── Navigation.php
            └── NavigationFactory.php
```

---

## Quick start

### 1. Tag routes

```php
// config/routes.php or a RouteProvider
$app->get('/admin', AdminHandler::class, 'admin.dashboard')
    ->setOptions([
        'navigation' => 'admin',
        'label'      => 'Dashboard',
        'icon'       => 'bi-grid-fill',
        'parent'     => null,   // top-level
        'order'      => 10,
    ]);

$app->get('/admin/users', UserListHandler::class, 'admin.users')
    ->setOptions([
        'navigation' => 'admin',
        'label'      => 'Users',
        'icon'       => 'bi-people-fill',
        'parent'     => null,
        'order'      => 20,
    ]);

$app->get('/admin/users/edit', UserEditHandler::class, 'admin.users.edit')
    ->setOptions([
        'navigation' => 'admin',
        'label'      => 'Edit User',
        'icon'       => 'bi-pencil',
        'parent'     => 'admin.users',   // nested under Users
        'order'      => 10,
    ]);
```

### 2. Use in a template

```php
// Sidebar menu
<?= $this->navigation('admin')->menu() ?>

// Breadcrumbs for the current page
<?= $this->navigation('admin')->breadcrumbs() ?>

// Sitemap-style flat tree
<?= $this->navigation('admin')->sitemap() ?>
```

### 3. Horizontal navbar variant

```php
<?= $this->navigation('main')->menu(['type' => 'horizontal']) ?>
```

---

## Route options reference

| Key | Type | Required | Description |
|---|---|---|---|
| `navigation` | `string\|string[]` | Yes | Nav identifier(s) this route belongs to. A route can belong to multiple navs. |
| `label` | `string` | No | Display text. Defaults to the route name. |
| `icon` | `string` | No | Bootstrap Icon class (e.g. `bi-grid-fill`). Empty string renders no icon. |
| `parent` | `string\|null` | No | Route **name** of the parent item for nested grouping. `null` = top-level. |
| `order` | `int` | No | Sort position within its level. Defaults to `0`. Lower values appear first. |

A route tagged with no `navigation` key is invisible to this package and passes through
the router normally.
