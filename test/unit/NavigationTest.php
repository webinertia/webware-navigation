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
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Webware\Navigation\View\Helper\Navigation;

#[CoversClass(Navigation::class)]
#[CoversMethod(Navigation::class, '__invoke')]
#[CoversMethod(Navigation::class, 'setRouter')]
final class NavigationTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function itemsLinkThroughTheRouterWhenOneIsSet(): void
    {
        $route = new Route('/docs/{version}', $this->createStub(MiddlewareInterface::class), ['GET'], 'docs');
        $route->setOptions(['navigation' => 'site', 'params' => ['version' => 'v2']]);

        $collector = $this->createStub(RouteCollectorInterface::class);
        $collector->method('getRoutes')->willReturn([$route]);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willReturn('/docs/v2');

        $helper = new Navigation(routeCollector: $collector);
        $helper->setRouter($router);

        $this->assertSame('/docs/v2', $helper('site')->getItems()[0]->getHref());
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function itemsLinkToTheRoutePathWithoutARouter(): void
    {
        $route = new Route('/docs/v2', $this->createStub(MiddlewareInterface::class), ['GET'], 'docs');
        $route->setOptions(['navigation' => 'site']);

        $collector = $this->createStub(RouteCollectorInterface::class);
        $collector->method('getRoutes')->willReturn([$route]);

        $this->assertSame('/docs/v2', new Navigation(routeCollector: $collector)('site')->getItems()[0]->getHref());
    }
}
