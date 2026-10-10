# Extending webware-navigation

## Custom renderers

Implement `RendererInterface` and pass it to the `Navigation` constructor by replacing
`NavigationFactory` in your own service-manager configuration.

---

## Implementing a custom renderer

```php
<?php

declare(strict_types=1);

namespace MyApp\Navigation\Renderer;

use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\Renderer\RendererInterface;

final class TailwindMenuRenderer implements RendererInterface
{
    public function render(NavigationContainer $container, array $options = []): string
    {
        $html = '<ul class="space-y-1">';

        foreach ($container as $item) {
            $html .= $this->renderItem($item, $container);
        }

        return $html . '</ul>';
    }

    private function renderItem(NavigationItem $item, NavigationContainer $container): string
    {
        $active = $container->isActive($item) ? ' bg-gray-100 font-semibold' : '';
        $icon   = $item->icon !== '' ? '<i class="' . $item->icon . '"></i> ' : '';

        $html = '<li><a class="flex items-center gap-2 px-3 py-2 rounded' . $active . '"'
              . ' href="' . htmlspecialchars($item->route->getPath(), ENT_QUOTES, 'UTF-8') . '">'
              . $icon
              . htmlspecialchars($item->label, ENT_QUOTES, 'UTF-8')
              . '</a>';

        if ($item->hasChildren()) {
            $html .= '<ul class="ml-4 space-y-1">';
            foreach ($item->getChildren() as $child) {
                $html .= $this->renderItem($child, $container);
            }
            $html .= '</ul>';
        }

        return $html . '</li>';
    }
}
```

---

## Wiring a custom renderer

Replace `NavigationFactory` in your own module's `ConfigProvider` with a factory that
injects the renderer:

```php
// Replaces Webware\Navigation\View\Helper\NavigationFactory, which is final
final class NavigationFactory
{
    public function __invoke(ContainerInterface $container): Navigation
    {
        return new Navigation(
            routeCollector: $container->get(RouteCollectorInterface::class),
            acl:            $container->get(AclInterface::class),
            menuRenderer:   new TailwindMenuRenderer(),
        );
    }
}
```

Register the override in your `ConfigProvider`:

```php
'view_helpers' => [
    'factories' => [
        Navigation::class => \MyApp\Navigation\NavigationFactory::class,
    ],
],
```

---


## Adding a new nav identifier

No code changes required. Define any string value for `options['navigation']`. Multiple
nav identifiers can share the same routes:

```php
$app->get('/home', HomeHandler::class, 'home')
    ->setOptions([
        'navigation' => ['main', 'footer'],  // appears in both navs
        'label'      => 'Home',
        'order'      => 1,
    ]);
```

Then render each independently:

```php
<?= $this->navigation('main')->menu(['type' => 'horizontal']) ?>
<?= $this->navigation('footer')->sitemap() ?>
```

---

## Overriding the ACL check

`NavigationFilterIterator` calls `AclInterface::isAllowed()`. To change
ACL filtering behaviour, implement `AclInterface` and register your implementation
in the DI container under `Webware\Core\AclInterface::class`. This affects all ACL consumers
including `AuthorizationMiddleware`.

To change filtering only for navigation without affecting the main ACL, wrap the
`Navigation` helper and override `__invoke` to use a different iterator or ACL
instance - then register your subclass via service-manager config.

---

## Sorting children

Children are appended in `addChild()` call order, which is the order they appear from
the filter iterator (which iterates the route collector's array). If you need children
sorted by `order`, sort them after tree-building in your `NavigationContainer`
subclass or renderer. The current implementation does not sort children - only
top-level items are sorted. This is a known limitation and a candidate for a future
improvement.
