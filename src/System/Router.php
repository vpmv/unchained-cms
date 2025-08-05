<?php

namespace App\System;

use App\System\Configuration\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Router MUST be included after RepoMan
 */
class Router
{
    /** @var array<string, Route> Routes => Application */
    protected array $routes;
    /**
     * @var array<string, Route> Application => Route <br>
     *                           Reverse routes only come in one locale,
     *                           and should be redirected accordingly
     */
    private string  $locale;
    protected array $routesReverse;

    public function __construct(private readonly Security $security, private readonly RouterInterface $sfRouter, private readonly RequestStack $requestStack, private readonly LocaleSwitcher $localeSwitcher)
    {
        $mainRequest = $requestStack->getCurrentRequest();
        if ($mainRequest->hasPreviousSession() && $locale = $mainRequest->getSession()->get('_locale')) {
            $this->locale = $locale;
        } else {
            $this->locale = $this->localeSwitcher->getLocale();
        }
    }

    public function addRoutes(Route ...$routes)
    {
        foreach ($routes as $route) {
            $this->routes[$route->getUri()]                                    = $route;
            $this->routesReverse[$route->getIdentifier()][$route->getLocale()] = $route;
        }
    }


    public function match(string $uri): Route
    {
        if (!isset($this->routes[$uri])) {
            throw new NotFoundHttpException('Could not resolve route to app.');
        }

        // clone Route preventing alterations
        $route = clone($this->routes[$uri]);
        $route->setAuthenticated($this->isAuthenticated());
        $route->setRequest($this->requestStack->getCurrentRequest());

        return $route;
    }

    public function matchApp(string $appId, ?string $childId = null, ?string $locale = null): Route|array
    {
        if ($childId) {
            if ($appId == '_default') {
                $appId = $childId;
            } else {
                $appId = Route::identifier($appId, $childId);
            }
        }

        if (!isset($this->routesReverse[$appId])) {
            throw new NotFoundHttpException("No routes for app <$appId>");
        }

        /** @var Route[] $routes */
        $routes = $this->routesReverse[$appId];

        // clone Route preventing alterations
        $route = clone($routes[$locale ?? $this->locale] ?? $routes[array_key_first($routes)]);
        $route->setAuthenticated($this->isAuthenticated());
        $route->setRequest($this->requestStack->getCurrentRequest());

        return $route;
    }

    /**
     * @return bool
     * @fixme factor out
     */
    public function isAuthenticated(): bool
    {
        return !empty($this->security->getToken()?->getRoleNames() ?? []);
    }

    public function setLocale(string $locale): void
    {
        if ($locale != '_default') {
            $this->locale = $locale;
        }
    }

    public function resolve(Route $route): ?Response
    {
        $redirect = null;
        $this->setLocale($route->getLocale()); // set Router locale for new routes
        if ($route->getLocale() != $this->localeSwitcher->getLocale()) {
            $request = $this->requestStack->getCurrentRequest();

            $this->localeSwitcher->setLocale($route->getLocale());
            $request->setLocale($route->getLocale());
            $request->getSession()->set('_locale', $route->getLocale());
            $request->attributes->set('_locale', $route->getLocale());
        }

        if ($route->isAuthenticationRequired() && !$route->isAuthenticated()) {
            $redirect = new RedirectResponse($this->sfRouter->generate('admin_login', ['redirect' => $route->getUri()]));
        }

        return $redirect;
    }
}