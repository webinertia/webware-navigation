# Component Reference

## NavigationItem

**Namespace:** `Webware\Navigation`  
**File:** `src/NavigationItem.php`

Immutable value object wrapping one `Route` with the navigation metadata extracted
from its options. Children are attached after construction during tree-building.

### Constructor

```php
public function __construct(
    public readonly Route   $route,
    public readonly string  $label,
    public readonly string  $icon,
    public readonly ?string $parent,  // route name of parent; null = top-level
    public readonly int     $order,
)
```

### Static factory

```php
NavigationItem::fromRouteOptions(Route $route, array $options): self
```

Reads `label`, `icon`, `parent`, `order` from `$options`. Defaults: `label` = route
name, `icon` = `''`, `parent` = `null`, `order` = `0`.

### Methods

| Method | Returns | Description |
|---|---|---|
| `addChild(NavigationItem)` | `void` | Appends a child. Called by `Navigation::__invoke` during tree build. |
| `getChildren()` | `list<NavigationItem>` | All direct children in insertion order. |
| `hasChildren()` | `bool` | `true` when at least one child exists. |

---

## NavigationFilterIterator

**Namespace:** `Webware\Navigation`  
**File:** `src/NavigationFilterIterator.php`  
**Extends:** `FilterIterator`

SPL filter iterator that accepts only routes belonging to a given nav identifier that
the current user's roles are permitted to access.

### Constructor

```php
public function __construct(
    array            $routes,   // list<Route> from RouteCollectorInterface::getRoutes()
    string           $navId,    // navigation identifier to filter for
    array            $roles,    // string[] current user's roles
    AclInterface     $acl,
)
```

### `accept(): bool`

Called internally by SPL for each item in the inner iterator. Returns `true` when:

1. `options['navigation']` equals `$navId` (string) or contains it (array).
2. `AclInterface::isAllowedByRouteName($route->getName(), $roles)` returns `true`.

Both conditions must be satisfied. If either fails the route is excluded.

---

## NavigationContainer

**Namespace:** `Webware\Navigation`  
**File:** `src/NavigationContainer.php`  
**Implements:** `IteratorAggregate<int, NavigationItem>`

Holds the resolved, ACL-filtered navigation tree for one nav identifier. Returned by
`Navigation::__invoke()`. Exposes rendering methods. Iterating it yields top-level
`NavigationItem` objects.

### Constructor

```php
public function __construct(
    array                $topLevel,           // list<NavigationItem>
    ?string              $activeRouteName,
    ?RendererInterface   $menuRenderer = null,
    ?RendererInterface   $breadcrumbRenderer = null,
    ?RendererInterface   $sitemapRenderer = null,
)
```

### Accessors

| Method | Returns | Description |
|---|---|---|
| `getItems()` | `list<NavigationItem>` | Top-level items. |
| `getActiveRouteName()` | `?string` | Matched route name for this request. |
| `isActive(NavigationItem)` | `bool` | `true` if item or any descendant matches active route. |

### Render methods

All three methods accept an optional `array $options` bag passed through to the
renderer or used by the inline fallback.

#### `menu(array $options = []): string`

Renders a Bootstrap 5 nav list. Delegates to `$menuRenderer` if set.

Inline fallback options:

| Key | Default | Description |
|---|---|---|
| `type` | `'sidebar'` | `'sidebar'` → `nav flex-column`; `'horizontal'` → `navbar-nav` |

Active items receive the `active` CSS class. If an item has children a nested
`<ul class="nav flex-column ms-3">` is emitted.

#### `breadcrumbs(array $options = []): string`

Renders a Bootstrap 5 `<nav aria-label="breadcrumb">` trail from the root to the
active item. Returns an empty string when no active item is found in the tree.
Delegates to `$breadcrumbRenderer` if set.

The trail is resolved by a depth-first search (`findTrail`): each top-level item is
visited; if the active route is found the ancestor chain is returned as
`[$ancestor, ..., $activeItem]`.

#### `sitemap(array $options = []): string`

Renders a plain `<ul class="ims-sitemap">` nested list of all items in the filtered
tree. Not ACL-filtered again — filtering was done at construction time. Delegates to
`$sitemapRenderer` if set.

---

## Navigation (view helper)

**Namespace:** `Webware\Navigation\View\Helper`  
**File:** `src/View/Helper/Navigation.php`  
**Implements:** `StatefulHelperInterface`

Registered in the `HelperPluginManager` under the alias `navigation`.

### Constructor

```php
public function __construct(
    RouteCollectorInterface  $routeCollector,
    AclInterface             $acl,
    ?RendererInterface       $menuRenderer = null,
    ?RendererInterface       $breadcrumbRenderer = null,
    ?RendererInterface       $sitemapRenderer = null,
)
```

The three `RendererInterface` parameters are `null` until a `RendererPluginManager` is
implemented (see [Extending / Custom Renderers](extending.md)).

### Per-request state methods (called by NavigationMiddleware)

| Method | Description |
|---|---|
| `setRoles(string[] $roles)` | Sets roles used for ACL filtering. |
| `setActiveRouteName(?string $name)` | Sets the matched route name for active detection. |
| `resetState()` | Clears roles and active route name. Called between requests in long-lived runtimes. |

### `__invoke(string $navId): NavigationContainer`

Builds and returns the container:

1. Creates `NavigationFilterIterator` with all routes, nav ID, current roles, and ACL.
2. Iterates the filtered routes, building `NavigationItem` objects keyed by route name.
3. Wires parent→child relationships.
4. Sorts top-level items by `order`.
5. Returns `new NavigationContainer($topLevel, $this->activeRouteName, ...)`.

---

## NavigationMiddleware

**Namespace:** `Webware\Navigation\Middleware`  
**File:** `src/Middleware/NavigationMiddleware.php`  
**Implements:** `MiddlewareInterface`

**Pipeline position:** after `UrlHelperMiddleware` (which runs after `RouteMiddleware`).
This guarantees `RouteResult` is on the request.

### Behaviour

1. Reads `UserInterface` from `$request->getAttribute(UserInterface::class)`.
   If present, calls `$helper->setRoles([...$user->getRoles()])`.
2. Reads `RouteResult` from `$request->getAttribute(RouteResult::class)`.
   If matched, calls `$helper->setActiveRouteName($routeResult->getMatchedRouteName())`.
3. Calls `$handler->handle($request)` — the helper is now primed for template use.

### Important: same helper instance

`NavigationMiddlewareFactory` fetches the `Navigation` helper from `HelperPluginManager`.
Laminas' plugin manager returns the **same shared instance** that templates receive.
This is why setting state on the helper in middleware is visible to the template — they
share the same object.

---

## RendererInterface

**Namespace:** `Webware\Navigation\Renderer`  
**File:** `src/Renderer/RendererInterface.php`

```php
interface RendererInterface
{
    public function render(NavigationContainer $container, array $options = []): string;
}
```

Implementations are resolved by a `RendererPluginManager` (planned). Register custom
renderers via service-manager configuration. See [Extending](extending.md).

---

## ConfigProvider

**Namespace:** `Webware\Navigation`  
**File:** `src/ConfigProvider.php`

Registers:

- `dependencies.factories`: `NavigationMiddleware::class → NavigationMiddlewareFactory`
- `view_helpers.aliases`: `'navigation' → Navigation::class`
- `view_helpers.factories`: `Navigation::class → NavigationFactory`

Register in `config/config.php`:

```php
Webware\Navigation\ConfigProvider::class,
```

---

## AclInterface additions

Two methods were added to `Webware\Acl\AclInterface` to support request-free ACL
checks from the filter iterator:

### `isAllowedByRouteName(string $routeName, $roles): bool`

Looks up `$routeName` in the internal route-to-resource/privilege mapping. Returns
`true` (visible) when the route has no ACL mapping — unmapped routes are not protected.
Returns `false` when the mapping exists and no role in `$roles` is permitted.

This is distinct from `isAllowedRoute(ServerRequestInterface, $roles)` which reads the
mapping from the live `RouteResult` on the request.
