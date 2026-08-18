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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\ConfigProvider;
use Webware\Navigation\Container\NavigationMiddlewareFactory;
use Webware\Navigation\Middleware\NavigationMiddleware;
use Webware\Navigation\View\Helper\Navigation;
use Webware\Navigation\View\Helper\NavigationFactory;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
final class ConfigProviderTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function providesDependencyAndViewHelperConfiguration(): void
    {
        $expected = [
            'dependencies' => [
                'factories' => [
                    NavigationMiddleware::class => NavigationMiddlewareFactory::class,
                ],
            ],
            'view_helpers' => [
                'aliases' => [
                    'navigation' => Navigation::class,
                ],
                'factories' => [
                    Navigation::class => NavigationFactory::class,
                ],
            ],
        ];

        $this->assertSame($expected, new ConfigProvider()->__invoke());
    }
}
