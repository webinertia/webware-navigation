<?php

declare(strict_types=1);

namespace Webware\Navigation\Middleware;

use Mezzio\Router\RouteResult;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Navigation\View\Helper\Navigation;
use Webware\UserManager\UserInterface;

use function is_string;

/**
 * Pipes per-request roles and the active route name into the Navigation helper.
 *
 * Must run AFTER RouteMiddleware so RouteResult is on the request.
 * Pipe in the global pipeline after UrlHelperMiddleware.
 */
final class NavigationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Navigation $helper,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);

        if (null !== $user) {
            $this->helper->setUser($user);
        }

        $acl = $request->getAttribute(AclInterface::class);

        if ($acl instanceof AclInterface) {
            $this->helper->setAcl($acl);
        }

        $routeResult = $request->getAttribute(RouteResult::class);

        if ($routeResult instanceof RouteResult) {
            $failed = $routeResult->isFailure();

            if (!$failed) {
                $matchedRouteName = $routeResult->getMatchedRouteName();
                $this->helper->setActiveRouteName(
                    is_string($matchedRouteName) && '' !== $matchedRouteName ? $matchedRouteName : null,
                );
            }
        }

        return $handler->handle($request);
    }
}
