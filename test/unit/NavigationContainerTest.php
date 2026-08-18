<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Navigation package.
 *
 * Copyright (c) 2025-2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebwareTest\Navigation;

use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\Renderer\RendererInterface;

use function iterator_to_array;

#[CoversClass(NavigationContainer::class)]
#[CoversMethod(NavigationContainer::class, 'getIterator')]
#[CoversMethod(NavigationContainer::class, 'getItems')]
#[CoversMethod(NavigationContainer::class, 'getActiveRouteName')]
#[CoversMethod(NavigationContainer::class, 'isActive')]
#[CoversMethod(NavigationContainer::class, 'menu')]
#[CoversMethod(NavigationContainer::class, 'breadcrumbs')]
#[CoversMethod(NavigationContainer::class, 'sitemap')]
final class NavigationContainerTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function menuMarksActiveItem(): void
    {
        $item = $this->makeItem('Dashboard', '/admin', 'admin.dashboard');
        $container = new NavigationContainer([$item], 'admin.dashboard');

        $html = $container->menu();

        $this->assertStringContainsString('active', $html);
        $this->assertStringContainsString('/admin', $html);
        $this->assertStringContainsString('Dashboard', $html);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function menuRendersChildNesting(): void
    {
        $parent = $this->makeItem('Users', '/admin/users', 'admin.users');
        $child = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $parent->addChild($child);

        $html = new NavigationContainer([$parent], null)->menu();

        $this->assertStringContainsString('<ul class="nav flex-column ms-3">', $html);
        $this->assertStringContainsString('/admin/users/edit', $html);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function breadcrumbsEmptyWithoutActiveRoute(): void
    {
        $item = $this->makeItem('Dashboard', '/admin', 'admin.dashboard');
        $container = new NavigationContainer([$item], null);

        $this->assertSame('', $container->breadcrumbs());
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function breadcrumbsReturnTrail(): void
    {
        $parent = $this->makeItem('Users', '/admin/users', 'admin.users');
        $child = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $parent->addChild($child);

        $html = new NavigationContainer([$parent], 'admin.users.edit')->breadcrumbs();

        $this->assertStringContainsString('Users', $html);
        $this->assertStringContainsString('Edit', $html);
        $this->assertStringContainsString('breadcrumb-item active', $html);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function sitemapRendersNestedList(): void
    {
        $parent = $this->makeItem('Users', '/admin/users', 'admin.users');
        $child = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $parent->addChild($child);

        $html = new NavigationContainer([$parent], null)->sitemap();

        $this->assertStringContainsString('<ul class="ims-sitemap">', $html);
        $this->assertStringContainsString('/admin/users/edit', $html);
    }

    /**
     * @throws \Exception
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function exposesItemsAndIterator(): void
    {
        $items = [$this->makeItem('Dashboard', '/admin', 'admin.dashboard')];
        $container = new NavigationContainer($items, null);

        $this->assertSame($items, $container->getItems());
        $this->assertSame($items, iterator_to_array($container->getIterator()));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function activeResolvesThroughChildren(): void
    {
        $parent = $this->makeItem('Users', '/admin/users', 'admin.users');
        $child = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $other = $this->makeItem('Reports', '/admin/reports', 'admin.reports');
        $parent->addChild($child);

        $container = new NavigationContainer([$parent, $other], 'admin.users.edit');

        $this->assertTrue($container->isActive($parent));
        $this->assertTrue($container->isActive($child));
        $this->assertFalse($container->isActive($other));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function menuDelegatesToRenderer(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn('<nav>rendered</nav>');

        $container = new NavigationContainer([], null, menuRenderer: $renderer);

        $this->assertSame('<nav>rendered</nav>', $container->menu(['type' => 'horizontal']));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function breadcrumbsAndSitemapDelegateToRenderers(): void
    {
        $breadcrumbRenderer = $this->createStub(RendererInterface::class);
        $breadcrumbRenderer->method('render')->willReturn('<ol>crumbs</ol>');
        $sitemapRenderer = $this->createStub(RendererInterface::class);
        $sitemapRenderer->method('render')->willReturn('<ul>map</ul>');

        $container = new NavigationContainer(
            [],
            null,
            breadcrumbRenderer: $breadcrumbRenderer,
            sitemapRenderer: $sitemapRenderer,
        );

        $this->assertSame('<ol>crumbs</ol>', $container->breadcrumbs());
        $this->assertSame('<ul>map</ul>', $container->sitemap());
    }

    /**
     * @param non-empty-string $path
     *
     * @throws \PHPUnit\Exception
     */
    private function makeItem(string $label, string $path, string $routeName): NavigationItem
    {
        $route = new Route($path, $this->createStub(MiddlewareInterface::class), ['GET'], $routeName);
        $route->setOptions(['label' => $label]);

        return NavigationItem::fromRouteOptions($route, $route->getOptions());
    }
}
