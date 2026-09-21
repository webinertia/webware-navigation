<?php

declare(strict_types=1);

namespace Webware\Navigation\Http\Middleware\Container;

use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Navigation\Http\Middleware\NavigationMiddleware;
use Webware\Navigation\View\Helper\Navigation;

final class NavigationMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): NavigationMiddleware
    {
        $helpers = $container->get(HelperPluginManager::class);

        return new NavigationMiddleware(
            helper: $helpers->get(Navigation::class),
        );
    }
}
