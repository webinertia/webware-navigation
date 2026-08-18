<?php

declare(strict_types=1);

namespace Webware\Navigation;

use Webware\Navigation\Container\NavigationMiddlewareFactory;
use Webware\Navigation\Middleware\NavigationMiddleware;
use Webware\Navigation\View\Helper\Navigation as NavigationHelper;
use Webware\Navigation\View\Helper\NavigationFactory;

final class ConfigProvider
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'view_helpers' => $this->getViewHelpers(),
        ];
    }

    /** @return array<string, mixed> */
    private function getDependencies(): array
    {
        return [
            'factories' => [
                NavigationMiddleware::class => NavigationMiddlewareFactory::class,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function getViewHelpers(): array
    {
        return [
            'aliases' => [
                'navigation' => NavigationHelper::class,
            ],
            'factories' => [
                NavigationHelper::class => NavigationFactory::class,
            ],
        ];
    }
}
