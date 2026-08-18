# Testing

## Conventions

- Unit tests: `WebwareTest\Navigation\` (`test/unit/`); integration tests:
  `WebwareTestIntegration\Navigation\` (`test/integration/`).
- `phpunit.xml.dist` sets `requireCoverageMetadata="true"` — every test class
  needs `#[CoversClass]` + `#[CoversMethod]` attributes.
- PHPUnit 13 mock-vs-stub rules apply: `createStub()` for value-returning
  doubles, `createMock()` only with `expects()`
  (see `.github/copilot-instructions.md`).

## Unit testing the filter iterator

**Blocked until dependencies publish:** these tests type against
`Webware\Acl\AclInterface` and `Webware\UserManager\UserInterface` — neither
package is published yet (see `docs/webware-tools-alignment.md`, Research
section). Skip until then.

`NavigationFilterIterator` is the easiest component to test in isolation. Construct
it with a stub `AclInterface` and an array of `Route` objects:

```php
use Mezzio\Router\Route;
use PHPUnit\Framework\TestCase;
use Webware\Acl\AclInterface;
use Webware\Navigation\NavigationFilterIterator;

final class NavigationFilterIteratorTest extends TestCase
{
    public function testExcludesRoutesNotInNav(): void
    {
        $route = $this->makeRoute('/admin', 'admin.dashboard', ['navigation' => 'admin']);
        $other = $this->makeRoute('/home', 'home', ['navigation' => 'main']);
        $acl   = $this->alwaysAllowAcl();

        $iterator = new NavigationFilterIterator([$route, $other], 'admin', ['admin'], $acl);
        $results  = iterator_to_array($iterator);

        self::assertCount(1, $results);
        self::assertSame('admin.dashboard', reset($results)->getName());
    }

    public function testExcludesRoutesNotAllowedByAcl(): void
    {
        $route = $this->makeRoute('/secret', 'admin.secret', ['navigation' => 'admin']);
        $acl   = $this->createStub(AclInterface::class);
        $acl->method('isAllowedByRouteName')->willReturn(false);

        $iterator = new NavigationFilterIterator([$route], 'admin', ['guest'], $acl);
        $results  = iterator_to_array($iterator);

        self::assertEmpty($results);
    }

    public function testAcceptsRouteInMultipleNavs(): void
    {
        $route = $this->makeRoute('/home', 'home', ['navigation' => ['main', 'footer']]);
        $acl   = $this->alwaysAllowAcl();

        $mainResults   = iterator_to_array(new NavigationFilterIterator([$route], 'main', ['guest'], $acl));
        $footerResults = iterator_to_array(new NavigationFilterIterator([$route], 'footer', ['guest'], $acl));

        self::assertCount(1, $mainResults);
        self::assertCount(1, $footerResults);
    }

    private function makeRoute(string $path, string $name, array $options): Route
    {
        $route = new Route($path, $this->createStub(\Psr\Http\Server\MiddlewareInterface::class), ['GET'], $name);
        $route->setOptions($options);
        return $route;
    }

    private function alwaysAllowAcl(): AclInterface
    {
        $acl = $this->createStub(AclInterface::class);
        $acl->method('isAllowedByRouteName')->willReturn(true);
        return $acl;
    }
}
```

---

## Unit testing NavigationContainer

```php
use PHPUnit\Framework\TestCase;
use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationItem;

final class NavigationContainerTest extends TestCase
{
    public function testMenuContainsActiveClass(): void
    {
        $item      = $this->makeItem('dashboard', '/admin', 'admin.dashboard');
        $container = new NavigationContainer([$item], 'admin.dashboard');

        self::assertStringContainsString('active', $container->menu());
    }

    public function testBreadcrumbsEmptyWhenNoActiveRoute(): void
    {
        $item      = $this->makeItem('Dashboard', '/admin', 'admin.dashboard');
        $container = new NavigationContainer([$item], null);

        self::assertSame('', $container->breadcrumbs());
    }

    public function testBreadcrumbsReturnsTrail(): void
    {
        $parent = $this->makeItem('Users', '/admin/users', 'admin.users');
        $child  = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $parent->addChild($child);

        $container = new NavigationContainer([$parent], 'admin.users.edit');
        $html      = $container->breadcrumbs();

        self::assertStringContainsString('Users', $html);
        self::assertStringContainsString('Edit', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    public function testMenuDelegatesToRenderer(): void
    {
        $renderer = $this->createMock(\Webware\Navigation\Renderer\RendererInterface::class);
        $renderer->expects(self::once())
                 ->method('render')
                 ->willReturn('<custom-menu/>');

        $container = new NavigationContainer([], null, $renderer);

        self::assertSame('<custom-menu/>', $container->menu());
    }

    private function makeItem(string $label, string $path, string $routeName): NavigationItem
    {
        $route = new \Mezzio\Router\Route(
            $path,
            $this->createStub(\Psr\Http\Server\MiddlewareInterface::class),
            ['GET'],
            $routeName
        );

        return new NavigationItem(
            route:  $route,
            label:  $label,
            icon:   '',
            parent: null,
            order:  0,
        );
    }
}
```

---

## Integration test notes

Tests that construct `Navigation` (the view helper) require a real or stub
`RouteCollectorInterface`. The simplest approach is an `ArrayObject`-backed stub:

```php
$routeCollector = $this->createStub(RouteCollectorInterface::class);
$routeCollector->method('getRoutes')->willReturn([$route1, $route2]);
```

Tests that verify ACL filtering end-to-end should use the real `Acl` class
(injected with a pre-built `Laminas\Permissions\Acl\Acl`) rather than a stub
so the fail-closed `isAllowed` logic is exercised — blocked until
`webware/acl` + `webware/usermanager` publish.

---

## What not to test

- `NavigationItem::fromRouteOptions` — trivial extraction; test behaviour at the
  container level instead.
- Inline HTML output character-for-character — assert on semantic content
  (`assertStringContainsString`) not exact markup.
- That `NavigationMiddleware` calls `$helper->setUser()` / `setAcl()` /
  `setActiveRouteName()` — this is covered implicitly by integration tests;
  unit-testing it only tests the middleware's one-liners.
