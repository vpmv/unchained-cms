<?php

namespace App\System\Configuration;

use Symfony\Component\HttpFoundation\Request;

enum RouteType: string
{
    case Category = 'dash_category';
    case Application = 'dash_application';
    case ApplicationDetail = 'dash_application_detail';
}

enum ApplicationType
{
    case Category;
    case Application;
}

class Route
{
    private string $uri      = '';
    private string $identifier;
    private bool   $impostor = false;

    private Request $request;

    public function __construct(
        public readonly ApplicationType $applicationType,
        private RouteType $type,
        private string $appId,
        private array $params,
        private string $locale,
        private bool $authenticationRequired = false,
        private bool $authenticated = false,
        private string $categoryId = '_default',
    ) {
        if (!$this->authenticationRequired) {
            $this->setAuthenticated(true);
        }

        // parse url & params
        if (!empty($this->params['application'])) {
            $this->uri = '/' . $this->params['application'];
        }
        if (!empty($this->params['category'])) {
            $this->uri = '/' . $this->params['category'] . $this->uri;
        }
        if (count($this->params) == 1) {
            $this->type   = RouteType::Category;
            $this->params = ['application' => $params[array_key_first($this->params)]];
        }

        $this->identifier = static::identifier($this->categoryId, $this->appId);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getType(): RouteType
    {
        return $this->type;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }


    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getName(): string
    {
        return $this->type->value;
    }

    public function getParam(string $key, $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function addParameters(array $parameters): void
    {
        if ($parameters['slug'] ?? false) {
            switch ($this->type) {
                case RouteType::Category:
                    $this->impostor = true;

                    $this->type                  = RouteType::Application;
                    $this->params['category']    = $this->params['application'];
                    $this->params['application'] = $parameters['slug'];
                    break;
                case RouteType::Application:
                    $this->type = RouteType::ApplicationDetail;
                    break;
            }
        }
        $this->params += $parameters;
    }

    public function getParams(bool $restore = false): array
    {
        if ($restore && $this->impostor) {
            return [
                'category'    => '_default',
                'application' => $this->params['category'],
                'slug'        => $this->params['application'],
            ];
        }
        return $this->params;
    }

    public function getQuery(string $key, mixed $default): mixed
    {
        return $this->request->query->get($key, $default);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function isAuthenticationRequired(): bool
    {
        return $this->authenticationRequired;
    }

    public function setAuthenticated(bool $isAuthenticated): void
    {
        $this->authenticated = $this->authenticationRequired ? $isAuthenticated : true;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return Route
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @param \App\System\Configuration\ApplicationConfig $config
     *
     * @return \App\System\Configuration\Route[]
     */
    public static function create(ApplicationConfig $config): array
    {
        /** @var Route[] $routes */
        $routes      = [];
        $routeConfig = $config->getRoutes();

        foreach ($routeConfig as $locale => $route) {
            $category  = $config->getCategory();
            $baseRoute = $category->getRoutes($locale);
            $params    = [];
            if ($baseRoute) {
                $params['category'] = $baseRoute;
            }

            $routes[] = new Route(
                ApplicationType::Category,
                RouteType::Category,
                $category->getCategoryId(),
                $params,
                $locale,
                authenticationRequired: !$category->isVisible(),
            );
            $routes[] = new Route(
                ApplicationType::Application,
                RouteType::Application,
                $config->getAppId(),
                $params + ['application' => $route],
                $locale,
                authenticationRequired: !$config->isPublic(),
                categoryId: $category->getCategoryId(),
            );
        }
        return $routes;
    }

    public static function identifier(string $categoryId, ?string $appId = null): string
    {
        return $categoryId && $categoryId != '_default' ? $categoryId . ':' . $appId : $appId;
    }
}