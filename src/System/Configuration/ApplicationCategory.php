<?php

namespace App\System\Configuration;

use App\System\Application\Property;

class ApplicationCategory
{
    /** @var string */
    private $description;
    /** @var string */
    private $label;
    /** @var bool */
    private $visible = true;

    private $routes = [];

    public Route $route;

    public function __construct(private string $categoryId, private array $config)
    {
        $this->description = Property::displayLabel($this->categoryId, 'category') . '.description';
        $this->label       = Property::displayLabel($this->categoryId, 'title.category');
        $this->setRoutes($this->config);
        $this->setVisibility($this->config);
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
     * Helper method defining category type
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->categoryId == '_default';
    }

    /**
     * @param string|null $locale
     *
     * @return string
     */
    public function getRoutes(?string $locale = null, bool $addSlash = false): string
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
