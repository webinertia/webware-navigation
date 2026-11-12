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

use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webware\Core\AclInterface;
use Webware\Navigation\View\Helper\Navigation;
use Webware\Navigation\View\Helper\NavigationFactory;

#[CoversClass(NavigationFactory::class)]
#[CoversMethod(NavigationFactory::class, '__invoke')]
final class NavigationFactoryTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function passesTheRegisteredAclToTheHelper(): void
    {
        $routeCollector = $this->createStub(RouteCollectorInterface::class);
        $acl            = $this->createStub(AclInterface::class);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): RouteCollectorInterface|AclInterface => AclInterface::class === $id
                    ? $acl
                    : $routeCollector,
            );

        static::assertInstanceOf(Navigation::class, new NavigationFactory()($container));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolvesTheHelperWhenNoAclServiceIsRegistered(): void
    {
        $routeCollector = $this->createStub(RouteCollectorInterface::class);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): RouteCollectorInterface => $routeCollector,
            );

        static::assertInstanceOf(Navigation::class, new NavigationFactory()($container));
    }
}
