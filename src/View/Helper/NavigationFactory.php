<?php

declare(strict_types=1);

namespace Webware\Navigation\View\Helper;

use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Acl\AclInterface;

final class NavigationFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Navigation
    {
        return new Navigation(
            routeCollector: $container->get(RouteCollectorInterface::class),
            acl           : $container->get(AclInterface::class),
            // Future: resolve from RendererPluginManager
            // menuRenderer:     $container->get(RendererPluginManager::class)->get(MenuRenderer::class),
            // breadcrumbRenderer: ...
            // sitemapRenderer:  ...
        );
    }
}
