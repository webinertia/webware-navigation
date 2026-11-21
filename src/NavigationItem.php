<?php

declare(strict_types=1);

namespace Webware\Navigation;

use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Router\Route;
use Mezzio\Router\RouterInterface;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Immutable value object representing a single resolved navigation item.
 *
 * Built from a Route whose options contain at minimum a 'navigation' key.
 * Children are populated after construction by Navigation view helper when
 * building the full navigation tree.
 *
 * Route options consumed:
 *   navigation  string|string[] - nav identifier(s) this item belongs to
 *   label       string - display text
 *   icon        string - Bootstrap Icon class (e.g. 'bi-grid-fill')
 *   parent      string|null - route name of the parent item; null = top-level
 *   order       int - sort order within its level (default 0)
 *   fragment    string - anchor to append to the route path, for a link to a section
 *   params      array - values for the route's own parameters, used when a router is present
 */
final class NavigationItem
{
    /** @var list<NavigationItem> */
    private array $children = [];

    /**
     * Anchor to append to the route path, for an item that links to a page section.
     *
     * Assigned by `fromRouteOptions()` rather than promoted, because the constructor is
     * already at the linter's parameter limit and the fragment is not part of an item's
     * identity: it says which part of the page to open, not which page. It is set once
     * from the same options array the other fields come from.
     */
    public ?string $fragment = null;

    /**
     * The router the path is generated through, when the item was built with one.
     *
     * Assigned by `fromRouteOptions()` for the same reason as the fragment. Without a
     * router the item links to its route's own path, which is only a usable address when
     * that path has no parameters.
     */
    public ?RouterInterface $router = null;

    /**
     * Values for the route's own parameters, from the `params` route option.
     *
     * @var array<string, mixed>
     */
    public array $params = [];

    public function __construct(
        public readonly Route $route,
        public readonly string $label,
        public readonly string $icon,
        public readonly ?string $parent,
        public readonly int $order,
    ) {}

    /**
     * @param array<array-key, mixed> $options Route::getOptions() result
     */
    public static function fromRouteOptions(Route $route, array $options, ?RouterInterface $router = null): self
    {
        $item = new self(
            route : $route,
            label : (string) ($options['label'] ?? $route->getName()),
            icon  : (string) ($options['icon'] ?? ''),
            parent: array_key_exists('parent', $options) && is_string($options['parent'])
                ? $options['parent']
                : null,
            order : (int) ($options['order'] ?? 0),
        );

        /** @var mixed $fragment Deliberately untyped: the check below decides what it is */
        $fragment = $options['fragment'] ?? null;

        // An empty fragment would render `href="/path#"`, which names no anchor and is not
        // the same link as `/path`, so it is treated as absent.
        $item->fragment = is_string($fragment) && '' !== $fragment ? $fragment : null;
        $item->router   = $router;

        /** @var mixed $params Deliberately untyped: the check below decides what it is */
        $params = $options['params'] ?? null;

        if (is_array($params)) {
            /** @var array<string, mixed> $params */
            $item->params = $params;
        }

        return $item;
    }

    public function addChild(NavigationItem $child): void
    {
        $this->children[] = $child;
    }

    /** @return list<NavigationItem> */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * The path to link to, carrying the item's anchor when it names a section.
     *
     * A section is the same route with an anchor appended, so the fragment travels
     * beside the path rather than inside it: a fragment in the route's own path
     * would register a route nothing can match and would pollute the container
     * that active-route detection reads.
     *
     * The path comes from the router when the item has one, so a route with parameters
     * links to its generated address and not to its raw pattern. Without a router it is
     * the route's own path.
     *
     * The fragment is used as given. Translating a heading into a slug belongs to
     * whoever writes the option, because the rule for that lives in the renderer
     * that produced the heading, not here.
     *
     * @throws RouterException When the router cannot generate the route's address
     */
    public function getHref(): string
    {
        $path = $this->path();

        if (null === $this->fragment) {
            return $path;
        }

        return "{$path}#{$this->fragment}";
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    /** @throws RouterException When the router cannot generate the route's address */
    private function path(): string
    {
        if (null === $this->router) {
            return $this->route->getPath();
        }

        return $this->router->generateUri($this->route->getName(), $this->params);
    }
}
