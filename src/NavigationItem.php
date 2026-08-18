<?php

declare(strict_types=1);

namespace Webware\Navigation;

use Mezzio\Router\Route;

use function array_key_exists;
use function is_string;

/**
 * Immutable value object representing a single resolved navigation item.
 *
 * Built from a Route whose options contain at minimum a 'navigation' key.
 * Children are populated after construction by Navigation view helper when
 * building the full navigation tree.
 *
 * Route options consumed:
 *   navigation  string|string[]  — nav identifier(s) this item belongs to
 *   label       string           — display text
 *   icon        string           — Bootstrap Icon class (e.g. 'bi-grid-fill')
 *   parent      string|null      — route name of the parent item; null = top-level
 *   order       int              — sort order within its level (default 0)
 */
final class NavigationItem
{
    /** @var list<NavigationItem> */
    private array $children = [];

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
    public static function fromRouteOptions(Route $route, array $options): self
    {
        return new self(
            route : $route,
            label : (string) ($options['label'] ?? $route->getName()),
            icon  : (string) ($options['icon'] ?? ''),
            parent: array_key_exists('parent', $options) && is_string($options['parent'])
                ? $options['parent']
                : null,
            order : (int) ($options['order'] ?? 0),
        );
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

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }
}
