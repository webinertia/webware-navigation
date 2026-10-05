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
the current user is permitted to access.

### Constructor

```php
public function __construct(
    array            $routes,   // list<Route> from RouteCollectorInterface::getRoutes()
    string           $navId,    // navigation identifier to filter for
    UserInterface    $user,    // current user object
    AclInterface     $acl,
)
```

### `accept(): bool`

Called internally by SPL for each item in the inner iterator. Returns `true` when:

1. `options['navigation']` equals `$navId` (string) or contains it (array).
2. The ACL allows the route name for the current user — `isAllowed()` with
   `$route->getName()` as the resource — returns `true`.

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
| `type` | `'sidebar'` | `'sidebar'` → `nav flex-column gap-1`; `'horizontal'` → `navbar-nav` |

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

`NavigationFactory` passes `null` for all three. With no renderer injected, each render
method uses its inline fallback (see [Extending](extending.md)).

### Per-request state methods (called by NavigationMiddleware)

| Method | Description |
|---|---|
| `setUser(?UserInterface $user)` | Sets the user passed to the ACL check. |
| `setAcl(AclInterface $acl)` | Replaces the ACL used for filtering. |
| `setActiveRouteName(?string $name)` | Sets the matched route name for active detection. |
| `resetState()` | Clears the user and the matched route name. Does not reset the ACL. Called between requests in long-lived runtimes. |

### `__invoke(string $navId): NavigationContainer`

Builds and returns the container:

1. Creates `NavigationFilterIterator` with all routes, nav ID, the current user, and the ACL.
2. Iterates the filtered routes, building `NavigationItem` objects keyed by route name.
3. Wires parent→child relationships.
4. Sorts top-level items by `order`.
5. Returns `new NavigationContainer($topLevel, $this->activeRouteName, ...)`.

---

## NavigationMiddleware

**Namespace:** `Webware\Navigation\Http\Middleware`  
**File:** `src/Http/Middleware/NavigationMiddleware.php`  
**Implements:** `MiddlewareInterface`

**Pipeline position:** after `UrlHelperMiddleware` (which runs after `RouteMiddleware`).
This guarantees `RouteResult` is on the request.

### Behaviour

1. Reads `UserInterface` from `$request->getAttribute(UserInterface::class)` and calls
   `$helper->setUser($user)`.
2. Reads `AclInterface` from `$request->getAttribute(AclInterface::class)` and calls
   `$helper->setAcl($acl)` when the attribute is set. `AclMiddleware` runs after this
   middleware in the shipped pipeline, so the attribute is not populated yet and the
   helper keeps the ACL that `NavigationFactory` injected.
3. Reads `RouteResult` from `$request->getAttribute(RouteResult::class)`. When it is
   present and not a failure, calls `$helper->setActiveRouteName()` with the matched
   route name, or `null` when that name is empty.
4. Calls `$handler->handle($request)` — the helper is now primed for template use.

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

Nothing resolves these from configuration: `Navigation` and `NavigationContainer` take
them as constructor arguments, and `NavigationFactory` passes `null`. See
[Extending](extending.md).

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

## The ACL check

The iterator asks the ACL directly, passing the route name as the resource:

```php
$this->acl->isAllowed(role: $this->user, resource: $route->getName());
```

`Webware\Core\AclInterface` extends `Laminas\Permissions\Acl\AclInterface`, so
`isAllowed()` is the inherited Laminas method, and `Acl::load()` registers resources
under their route names — which is why the route name is what gets passed.

The interface itself adds `isAllowedRoute(?UserInterface $user, ResourceInterface
$resource)`, a thin wrapper over the same call for callers that already hold a
`ResourceInterface`.

There is no route-name variant on the interface: one would duplicate
`isAllowedRoute()` in all but name.
