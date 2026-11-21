<?php

declare(strict_types=1);

namespace Webware\Navigation\View\Helper;

use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Core\AclInterface;

final class NavigationFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Navigation
    {
        $helper = new Navigation(
            routeCollector: $container->get(RouteCollectorInterface::class),
            acl           : $container->has(AclInterface::class) ? $container->get(AclInterface::class) : null,
            // Future: resolve from RendererPluginManager
            // menuRenderer:     $container->get(RendererPluginManager::class)->get(MenuRenderer::class),
            // breadcrumbRenderer: ...
            // sitemapRenderer:  ...
        );

        if ($container->has(RouterInterface::class)) {
            $helper->setRouter($container->get(RouterInterface::class));
        }

        return $helper;
    }
}
