# Architecture and Design Decisions

## The core problem

Mezzio applications define routes in PHP files. Those routes carry no display metadata.
Traditional navigation systems maintain a **separate** configuration array that mirrors
the route list: labels, icons, URLs, ACL resources. This creates two places to update
whenever a route changes and a persistent risk that navigation shows links the current
user is not permitted to follow.

---

## Decision 1 — Route options as the single source of truth

`Mezzio\Router\Route::setOptions(array $options)` is an arbitrary key/value bag
intended for exactly this kind of extension. Rather than a parallel config array, nav
metadata lives on the route itself:

```php
$app->get('/admin', AdminHandler::class, 'admin.dashboard')
    ->setOptions([
        'navigation' => 'admin',
        'label'      => 'Dashboard',
        'icon'       => 'bi-grid-fill',
        'order'      => 10,
    ]);
```

**Why this is correct:**
- Route definition and nav metadata are co-located — one change, one place.
- `RouteCollectorInterface::getRoutes()` returns all registered `Route` objects, so
  any service with `RouteCollectorInterface` can walk the full route list and extract
  nav items. No additional registry or config key is needed.
- FastRoute caches only the dispatch table (method→path→handler). Route options live
  on the PHP `Route` objects rebuilt from `RouteProvider` definitions each request.
  They are always current and never stale.

**Rejected alternatives:**
- Separate `navigation.config.php` — duplicates route definitions, desynchronises easily.
- Database-driven navigation — adds a DB round-trip to every page render with no benefit
  since the route list is static code.

---

## Decision 2 — SPL FilterIterator for filtering

All filtering (nav ID membership + ACL) is concentrated in `NavigationFilterIterator`,
a `FilterIterator` subclass wrapping `ArrayIterator<Route>`.

```
RouteCollectorInterface::getRoutes()  →  list<Route>
                                              ↓
                               NavigationFilterIterator
                               (nav ID check + ACL check)
                                              ↓
                               filtered iterable of Route
```

**Why FilterIterator over a foreach loop inside the helper:**
- Separates the *what-to-include* policy from the *what-to-do-with-it* logic.
- `NavigationFilterIterator` can be independently unit-tested by constructing it with
  a stub `AclInterface` — no view renderer, no helper wiring needed.
- The helper's `__invoke` becomes clean orchestration: create iterator, build tree,
  return container.

**Why a subclass over `CallbackFilterIterator` with a closure:**
- The subclass makes the dependency on `AclInterface` explicit and injectable.
- `accept()` is a named method, easier to read in stack traces and test doubles.

**Lazy vs. eager materialisation:**
The iterator itself is lazy — `accept()` is only called as the `foreach` in
`Navigation::__invoke` pulls items. However the tree-building step (parent→child wiring)
must materialise all items before assigning children, because a child's parent may
appear after it in the route list. Two-pass materialisation is unavoidable for tree
construction. This is not a performance concern: route lists are small (dozens to low
hundreds of items) and the work is O(n).

---

## Decision 3 — NavigationItem as a tree-aware DTO

`Route` carries its path and options but has no concept of a navigation tree. The
parent→child relationship must be tracked somewhere. `NavigationItem` is a thin wrapper
that:

- Extracts typed fields (`label`, `icon`, `parent`, `order`) from `getOptions()` once,
  so templates never call `$route->getOptions()['label'] ?? ''`.
- Holds a mutable `$children` list populated during tree construction.
- Retains a reference to the original `Route` so templates can call
  `$item->route->getPath()` and `$item->route->getName()` without extra lookups.

**Why not return bare `Route[]` from the helper:**
Callers would need to call `getOptions()` repeatedly in templates, coupling templates
to the options key names and making null-coalescing boilerplate unavoidable. The DTO
provides a stable, typed surface.

**Why `addChild()` is mutable while the rest is `readonly`:**
PHP does not support lazy `readonly` initialisation. The two-pass tree construction
algorithm requires writing children after the parent item is constructed. Limiting
mutability to `addChild()` — a package-internal call — keeps the surface safe.

---

## Decision 4 — NavigationContainer as the render surface

The filtered tree is wrapped in `NavigationContainer` rather than returned as a plain
array. This enables:

```php
$this->navigation('admin')->menu()
$this->navigation('admin')->breadcrumbs()
$this->navigation('admin')->sitemap()
```

All three representations share the **same filtered, ACL-checked tree** built once per
`__invoke` call. If a user cannot see route X in a menu they cannot see it in a sitemap
or breadcrumb trail either — ACL is applied once at the container level.

`NavigationContainer` implements `IteratorAggregate` over its top-level items. This
allows internal renderer code to `foreach` the container without exposing the raw array,
and keeps the API open for future renderer implementations.

**Why not expose a generator from `__invoke`:**
A generator would force the tree-building step to buffer all items anyway (parent→child
wiring requires all items to be in memory). A generator here buys nothing and would
make the API harder to use — callers cannot call `->menu()` on a generator.

---

## Decision 5 — RendererInterface injection points

Each render method (`menu`, `breadcrumbs`, `sitemap`) checks for an optional
`RendererInterface` before falling back to inline Bootstrap 5 markup.

```
NavigationContainer::menu()
    → if $menuRenderer !== null: $menuRenderer->render($this, $options)
    → else: inline Bootstrap markup
```

The renderers are constructor parameters on `NavigationContainer`, injected by the
`Navigation` helper, which receives them from `NavigationFactory`. The factory passes
`null` for all three, so the inline fallbacks run.

**Why plan for renderers now rather than deferring entirely:**
- The constructor signature is the public API surface. Adding renderer parameters
  later would be a breaking change.
- The `null` default means zero cost until renderers exist.
- The `RendererInterface` contract is documented so implementors know what to build.

A custom renderer is supplied by replacing `NavigationFactory` in the consumer's
service-manager configuration and passing it to the shown parameter. See
[Extending](extending.md).

---

## Decision 6 — StatefulHelperInterface for per-request state

The `Navigation` view helper implements `Laminas\View\Helper\StatefulHelperInterface`.
This pattern is used throughout the Laminas/Mezzio ecosystem (e.g. `UrlHelper`) for
helpers that need per-request state injected after construction but before invocation.

`NavigationMiddleware` calls `$helper->setUser()`, `$helper->setAcl()` and
`$helper->setActiveRouteName()` on the **same shared instance** the DI container holds. The helper is a long-lived
service; `resetState()` is called by Laminas' `HelperPluginManager` between requests
(in long-lived runtimes) to prevent state leakage.

**Why not inject the request directly into the helper:**
Helpers are services — injecting `ServerRequestInterface` would give them the request
at construction time, which in a request-response lifecycle is either wrong (constructed
once, request changes) or forces factory complexity. The `StatefulHelperInterface`
pattern cleanly separates construction from per-request state.

---

## Decision 7 — NavigationMiddleware placement: after UrlHelperMiddleware

`NavigationMiddleware` must run **after `RouteMiddleware`** so that
`RouteResult::class` is already on the request when the middleware reads the matched
route name for active-item detection.

It is placed after `UrlHelperMiddleware` (which itself runs after `RouteMiddleware`) in
the global pipeline:

```
RouteMiddleware           ← sets RouteResult on request
ImplicitHeadMiddleware
ImplicitOptionsMiddleware
MethodNotAllowedMiddleware
UrlHelperMiddleware       ← seeds UrlHelper with route result
NavigationMiddleware      ← reads RouteResult::getMatchedRouteName()
DispatchMiddleware
```

Placing it before `RouteMiddleware` (as was briefly the case during development) means
`RouteResult` is always `null`, making active-item detection silently non-functional.

---

## Decision 8 — How the filter iterator checks the ACL

`NavigationFilterIterator` runs inside a renderer context and inspects routes before
any of them is dispatched, so it has no request and cannot build the `RouteResource`
that `isAllowedRoute()` takes. It calls the ACL directly, passing the route name as
the resource:

```php
$this->acl->isAllowed(role: $this->user, resource: $route->getName());
```

Resource ids are route names — `Acl::load()` registers every route from the route
collector — so a route name is a valid id and resolves to the same rule an
`isAllowedRoute()` call would find. There is no route-name variant on the interface:
one would duplicate `isAllowedRoute()` in all but name.

Three properties of `Acl::isAllowed()` decide the result:

1. **Fail-closed** — a resource id the ACL has not registered is denied before
   Laminas sees it.
2. **Lazy load** — the first call materialises the rules from the `acl_rule` table
   and registers the routes as resources.
3. **Ancestor rules apply** — a route with no rule of its own inherits from its
   nearest registered ancestor, so rules on an anchor such as `user` cover the
   routes beneath it.

The result decides visibility, so the ACL rows and `Route::setOptions(['navigation'
=> ...])` have to agree: a nav entry whose route the ACL denies is filtered out
before render.

## Active-item detection algorithm

Active detection is recursive. A parent item is considered active if any of its
descendants match the current route name. This produces correct sidebar highlighting
for nested navigation:

```
admin.users      ← active (because descendant is active)
  admin.users.edit  ← active (matched route)
```

`NavigationContainer::isActive(NavigationItem)` walks the tree depth-first. The private
`findTrail()` helper does the same for breadcrumbs, returning the ancestor chain from the
root to the active item.

---

## Data flow summary

```
Request
  │
  ▼
NavigationMiddleware
  setUser($user)
  setAcl($acl)                     — only when the attribute is already set
  setActiveRouteName('admin.users.edit')
  │
  ▼
Template: $this->navigation('admin')
  │
  ▼
Navigation::__invoke('admin')
  │
  ├── NavigationFilterIterator(routes, 'admin', $user, $acl)
  │     forEach route:
  │       belongsToNav(options, 'admin') ?
  │       isAllowed(role: $user, resource: routeName) ?
  │
  ├── Build NavigationItem objects from filtered routes
  ├── Wire parent→child relationships
  ├── Sort top-level by order
  │
  └── return NavigationContainer($topLevel, 'admin.users.edit')
        │
        ├── ->menu()         Bootstrap sidebar / navbar HTML
        ├── ->breadcrumbs()  Bootstrap breadcrumb trail HTML
        └── ->sitemap()      Plain nested list HTML
```
