<?php

declare(strict_types=1);

namespace Webware\Navigation\View\Helper;

use Laminas\View\Helper\StatefulHelperInterface;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Acl\AclInterface;
use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationFilterIterator;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\Renderer\RendererInterface;
use Webware\UserManager\UserInterface;

use function array_key_exists;
use function usort;

/**
 * View helper that builds an ACL-filtered NavigationContainer for a given
 * navigation identifier.
 *
 * Roles and the active route name are injected per-request by
 * NavigationMiddleware (which must run after RouteMiddleware).
 *
 * Usage in templates:
 *   <?= $this->navigation('admin')->menu() ?>
 *   <?= $this->navigation('admin')->breadcrumbs() ?>
 *
 * Renderers are optional. When non-null they override the inline fallback
 * markup in NavigationContainer. Inject custom renderers via NavigationFactory
 * by fetching them from a RendererPluginManager (planned — see NavigationFactory).
 */
final class Navigation implements StatefulHelperInterface
{
    private ?UserInterface $user = null;

    private ?string $activeRouteName = null;

    public function __construct(
        private readonly RouteCollectorInterface $routeCollector,
        private AclInterface $acl,
        // Future injection points — NavigationFactory will resolve these from
        // a RendererPluginManager once renderers are implemented.
        private readonly ?RendererInterface $menuRenderer = null,
        private readonly ?RendererInterface $breadcrumbRenderer = null,
        private readonly ?RendererInterface $sitemapRenderer = null,
    ) {}

    #[Override]
    public function resetState(): void
    {
        $this->user            = null;
        $this->activeRouteName = null;

        // acl is intentionally not reset — it is repopulated each request by NavigationMiddleware
    }

    public function setAcl(AclInterface $acl): void
    {
        $this->acl = $acl;
    }

    public function setActiveRouteName(?string $name): void
    {
        $this->activeRouteName = $name;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    /**
     * Builds and returns an ACL-filtered NavigationContainer for $navId.
     *
     * The container lazily renders via ->menu(), ->breadcrumbs(), ->sitemap().
     */
    public function __invoke(string $navId): NavigationContainer
    {
        $iterator = new NavigationFilterIterator(
            $this->routeCollector->getRoutes(),
            $navId,
            $this->user,
            $this->acl,
        );

        /** @var array<string, NavigationItem> $allItems keyed by route name */
        $allItems = [];

        foreach ($iterator as $route) {
            $allItems[$route->getName()] = NavigationItem::fromRouteOptions($route, $route->getOptions());
        }

        $topLevel = [];

        foreach ($allItems as $item) {
            if (null !== $item->parent && array_key_exists($item->parent, $allItems)) {
                $allItems[$item->parent]->addChild($item);
                continue;
            }

            $topLevel[] = $item;
        }

        usort($topLevel, static fn(NavigationItem $a, NavigationItem $b): int => $a->order <=> $b->order);

        return new NavigationContainer(
            $topLevel,
            $this->activeRouteName,
            $this->menuRenderer,
            $this->breadcrumbRenderer,
            $this->sitemapRenderer,
        );
    }
}
