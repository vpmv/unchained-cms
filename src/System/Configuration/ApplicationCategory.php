<?php

namespace App\System\Configuration;

use App\System\Application\Property;

class ApplicationCategory
{
    /** @var string */
    private $categoryId;
    /** @var string */
    private $description;
    /** @var string */
    private $label;
    /** @var bool */
    private $visible = true;

    private $routes = [];

    public function __construct(string $categoryId, array $config)
    {
        $this->categoryId  = $categoryId;
        $this->description = Property::displayLabel($this->categoryId, 'category') . '.description';
        $this->label       = Property::displayLabel($this->categoryId, 'title.category');
        $this->setRoutes($config);
        $this->setVisibility($config);
    }

    public function getCategoryId()
    {
        return $this->categoryId;
    }

    /**
     * @return array
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param string|null $locale
     *
     * @return string
     */
    public function getRoute(?string $locale = null, bool $addSlash = false): string
    {
        $route = $this->routes['_default'];
        if ($locale && isset($this->routes[$locale])) {
            $route = $this->routes[$locale];
        }
        if ($route && $addSlash) {
            $route .= '/';
        }

        return $route;
    }

    /**
     * @param array $routes
     */
    private function setRoutes(array $config): void
    {
        $this->routes = [
                '_default' => $this->categoryId == '_default' ? '' : $this->categoryId,
            ] + ($config['routes'] ?? []);

        foreach ($this->routes as &$route) {
            $route = preg_replace('/[\W_]+/', '-', $route);
        }
    }

    private function setVisibility(array $config): void
    {
        $this->visible = filter_var($config['public'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }
}
