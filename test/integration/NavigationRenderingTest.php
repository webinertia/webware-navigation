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

namespace WebwareTestIntegration\Navigation;

use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationItem;

#[CoversClass(NavigationContainer::class)]
#[CoversClass(NavigationItem::class)]
#[CoversMethod(NavigationContainer::class, 'menu')]
#[CoversMethod(NavigationContainer::class, 'breadcrumbs')]
#[CoversMethod(NavigationContainer::class, 'sitemap')]
#[CoversMethod(NavigationItem::class, 'fromRouteOptions')]
#[CoversMethod(NavigationItem::class, 'addChild')]
final class NavigationRenderingTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function fullTreeRendersAcrossAllOutputs(): void
    {
        $top = $this->makeItem('Dashboard', '/admin', 'admin.dashboard');
        $users = $this->makeItem('Users', '/admin/users', 'admin.users');
        $userEdit = $this->makeItem('Edit', '/admin/users/edit', 'admin.users.edit');
        $userRoles = $this->makeItem('Roles', '/admin/users/roles', 'admin.users.roles');
        $users->addChild($userEdit);
        $users->addChild($userRoles);
        $top->addChild($users);

        $container = new NavigationContainer([$top], 'admin.users.edit');

        $menu = $container->menu();
        $breadcrumbs = $container->breadcrumbs();
        $sitemap = $container->sitemap();

        $this->assertStringContainsString('nav-link active', $menu);
        $this->assertStringContainsString('/admin/users/roles', $menu);
        $this->assertStringContainsString('Users', $breadcrumbs);
        $this->assertStringContainsString('breadcrumb-item active', $breadcrumbs);
        $this->assertStringContainsString('<ul class="ims-sitemap">', $sitemap);
        $this->assertStringContainsString('/admin/users/edit', $sitemap);
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
