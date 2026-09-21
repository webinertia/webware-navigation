<?php

declare(strict_types=1);

namespace Webware\Navigation;

use ArrayIterator;
use FilterIterator;
use Mezzio\Router\Route;
use Override;
use Webware\Core\AclInterface;
use Webware\Core\UserInterface;

use function in_array;
use function is_array;
use function is_string;

/**
 * Filters a list<Route> to those belonging to a given navigation identifier
 * that the current user is ACL-permitted to access.
 *
 * Decouples ACL evaluation from the Navigation view helper — the helper only
 * iterates; this class decides what is visible.
 *
 * @extends FilterIterator<int, Route, ArrayIterator<int, Route>>
 */
final class NavigationFilterIterator extends FilterIterator
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        array $routes,
        private readonly string $navId,
        private readonly UserInterface $user,
        private readonly AclInterface $acl,
    ) {
        parent::__construct(new ArrayIterator($routes));
    }

    /** @param array<array-key, mixed> $options */
    private static function belongsToNav(array $options, string $navId): bool
    {
        /** @var mixed $nav */
        $nav = $options['navigation'] ?? null;

        if (is_string($nav)) {
            return $nav === $navId;
        }

        if (is_array($nav)) {
            return in_array($navId, $nav, strict: true);
        }

        return false;
    }

    #[Override]
    public function accept(): bool
    {
        /** @var Route $route */
        $route = $this->current();

        $options = $route->getOptions();

        return (
            self::belongsToNav($options, $this->navId)
                && $this->acl->isAllowed(
                    role    : $this->user,
                    resource: $route->getName(),
                )
        );
    }
}
