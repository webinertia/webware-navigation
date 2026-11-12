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
use Webware\Core\AclInterface;
use Webware\Navigation\NavigationFilterIterator;

use function array_map;
use function iterator_to_array;

#[CoversClass(NavigationFilterIterator::class)]
#[CoversMethod(NavigationFilterIterator::class, '__construct')]
#[CoversMethod(NavigationFilterIterator::class, 'accept')]
final class NavigationFilterIteratorTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function acceptsEveryRouteNamingTheNavIdWhenNoAclIsPresent(): void
    {
        $iterator = new NavigationFilterIterator(
            [
                $this->makeRoute('admin.dashboard', '/admin', ['navigation' => 'admin']),
                $this->makeRoute('admin.users', '/admin/users', ['navigation' => ['admin', 'sidebar']]),
            ],
            'admin',
            null,
            null,
        );

        static::assertSame(['admin.dashboard', 'admin.users'], $this->routeNames($iterator));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function appliesAclAllowAndDenyWhenAnAclIsPresent(): void
    {
        $acl = $this->createStub(AclInterface::class);
        $acl->method('isAllowed')->willReturnCallback(
            static fn(mixed $role = null, mixed $resource = null): bool => 'admin.dashboard' === $resource,
        );

        $iterator = new NavigationFilterIterator(
            [
                $this->makeRoute('admin.dashboard', '/admin', ['navigation' => 'admin']),
                $this->makeRoute('admin.users', '/admin/users', ['navigation' => 'admin']),
                $this->makeRoute('public.home', '/', ['navigation' => 'public']),
            ],
            'admin',
            null,
            $acl,
        );

        static::assertSame(['admin.dashboard'], $this->routeNames($iterator));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function rejectsRoutesNamingAnotherNavIdWhenNoAclIsPresent(): void
    {
        $iterator = new NavigationFilterIterator(
            [
                $this->makeRoute('admin.dashboard', '/admin', ['navigation' => 'admin']),
                $this->makeRoute('public.home', '/', ['navigation' => 'public']),
                $this->makeRoute('unnamed.nav', '/unnamed', []),
            ],
            'admin',
            null,
            null,
        );

        static::assertSame(['admin.dashboard'], $this->routeNames($iterator));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \PHPUnit\Exception
     */
    private function makeRoute(string $name, string $path, array $options): Route
    {
        $route = new Route($path, $this->createStub(MiddlewareInterface::class), ['GET'], $name);
        $route->setOptions($options);

        return $route;
    }

    /**
     * @return list<string>
     */
    private function routeNames(NavigationFilterIterator $iterator): array
    {
        return array_map(
            static fn(Route $route): string => $route->getName(),
            iterator_to_array($iterator, preserve_keys: false),
        );
    }
}
