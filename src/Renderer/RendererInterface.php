<?php

declare(strict_types=1);

namespace Webware\Navigation\Renderer;

use Webware\Navigation\NavigationContainer;

/**
 * Contract for all navigation renderers.
 *
 * Implementations are resolved from a RendererPluginManager (planned) keyed by
 * their FQCN or alias. Register custom renderers via `view_helper_config` /
 * service-manager config in ConfigProvider.
 *
 * @see NavigationContainer::menu()
 * @see NavigationContainer::breadcrumbs()
 * @see NavigationContainer::sitemap()
 */
interface RendererInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function render(NavigationContainer $container, array $options = []): string;
}
