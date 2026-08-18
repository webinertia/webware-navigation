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
use Webware\Navigation\NavigationItem;

#[CoversClass(NavigationItem::class)]
#[CoversMethod(NavigationItem::class, 'fromRouteOptions')]
#[CoversMethod(NavigationItem::class, 'addChild')]
#[CoversMethod(NavigationItem::class, 'getChildren')]
#[CoversMethod(NavigationItem::class, 'hasChildren')]
final class NavigationItemTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function fallsBackToDefaults(): void
    {
        $item = NavigationItem::fromRouteOptions($this->makeRoute('admin.dashboard', []), []);

        $this->assertSame('admin.dashboard', $item->label);
        $this->assertSame('', $item->icon);
        $this->assertNull($item->parent);
        $this->assertSame(0, $item->order);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function consumesOptionValues(): void
    {
        $item = NavigationItem::fromRouteOptions(
            $this->makeRoute('admin.users', []),
            ['label' => 'Users', 'icon' => 'bi-people', 'parent' => 'admin', 'order' => 3],
        );

        $this->assertSame('Users', $item->label);
        $this->assertSame('bi-people', $item->icon);
        $this->assertSame('admin', $item->parent);
        $this->assertSame(3, $item->order);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function ignoresNonStringParent(): void
    {
        $item = NavigationItem::fromRouteOptions(
            $this->makeRoute('admin.users', []),
            ['parent' => 5],
        );

        $this->assertNull($item->parent);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function managesChildren(): void
    {
        $parent = NavigationItem::fromRouteOptions($this->makeRoute('admin', []), []);
        $child = NavigationItem::fromRouteOptions($this->makeRoute('admin.users', []), []);

        $this->assertFalse($parent->hasChildren());

        $parent->addChild($child);

        $this->assertTrue($parent->hasChildren());
        $this->assertSame([$child], $parent->getChildren());
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws \PHPUnit\Exception
     */
    private function makeRoute(string $name, array $options): Route
    {
        $route = new Route('/path', $this->createStub(MiddlewareInterface::class), ['GET'], $name);
        $route->setOptions($options);

        return $route;
    }
}
