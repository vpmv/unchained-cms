<?php

namespace App\System\Configuration;

use App\System\Application\Field;
use App\System\Application\Property;
use InvalidArgumentException;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ApplicationConfig
{
    use YamlReader;

    /** @var string Application identifier */
    public string $appId;
    /** @var \App\System\Configuration\ApplicationCategory */
    private ApplicationCategory $category;
    /** @var array Configuration */
    private array $config = [];

    /** @var Field[] */
    public array $fields  = [];
    public array $sort    = [];
    public array $sources = [];
    public array $modules = [];
    public array $meta    = [];

    /** @var array Raw config */
    private array $raw;

    public function __construct(ConfigStore $configStore, array $configuration, string $appId, string $projectDir)
    {
        $this->basePath = $projectDir . '/config/';

        $this->appId = $appId;
        $this->raw   = $configuration + ['appId' => $appId, 'name' => $appId];

        $this->setSources();
        $this->setModules();
        $this->prepareFields($configStore);
        $this->prepareFrontend($configStore);
        $this->setCategory($configStore);
    }

    public function isPublic(): bool {
        return $this->raw['public'] ?? true;
    }

    /**
     * @return string
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * @return \App\System\Configuration\ApplicationCategory
     */
    public function getCategory(): ApplicationCategory
    {
        return $this->category;
    }

    private function setCategory(ConfigStore $configStore): void
    {
        $this->category = $configStore->getCategoryConfig($this->raw['category'] ?? '_default');
    }

    /**
     * @param string $type
     *
     * @return array
     */
    public function getConstraint(string $type): array
    {
        return $this->config['constraints'][$type] ?? [];
    }

    /**
     * @param string $field
     *
     * @return \App\System\Application\Field
     */
    public function getField(string $field): Field
    {
        if (!isset($this->fields[$field])) {
            throw new InvalidArgumentException("Unconfigured field $field in application " . $this->getAppId());
        }

        return $this->fields[$field];
    }

    /**
     * @return \App\System\Application\Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string|null $locale
     *
     * @return array|string
     */
    public function getRoutes(?string $locale = null): array|string
    {
        $routes = [
            '_default' => $this->appId,
        ];
        if (isset($this->raw['routes'])) {
            $routes += $this->raw['routes'];
        }
        if ($locale) {
            return $routes[$locale] ?? $routes['_default'];
        }

        return $routes;
    }

    /**
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @param string $alias
     *
     * @return array
     */
    public function getSource(string $alias): array
    {
        if (!isset($this->sources[$alias])) {
            throw new InvalidArgumentException('Unknown source alias: ' . $alias);
        }

        return $this->sources[$alias];
    }

    /**
     * @return array
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function getModule(string $module): ?array
    {
        if (!isset($this->modules[$module]) || !($this->modules[$module]['enabled'] ?? false)) {
            return null;
        }

        return $this->modules[$module];
    }

    /**
     * @return array
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * @param string|null $attribute
     *
     * @return array|null
     */
    public function getMeta(?string $attribute = null): ?array
    {
        return $attribute ? ($this->meta[$attribute] ?? null) : $this->meta;
    }

    protected function setSources(): void
    {
        $sources = $this->raw['sources'] ?? [];
        foreach ($sources as $name => &$source) {
            if (empty($source['application'])) {
                continue;
            }

            $source['join_source'] = $source['join_source'] ?? null;
            $source['function']    = $source['function'] ?? null;
            $source['fields']      = $source['fields'] ?? [];
            $source['pointer']     = $source['pointer'] ?? 'detail';
            $foreignColumn         = $source['foreign_column'] ?? Property::foreignKey($source['application']);
            $localColumn           = $source['column'] ?? ($source['function'] ? Property::foreignKey($this->appId) : 'id');

            if ($source['invert_join'] ?? false) {
                $localColumn   = Property::foreignKey($this->appId);
                $foreignColumn = 'id';
            }

            $source['foreign_column'] = $foreignColumn;
            $source['column']         = $localColumn;
            if ($source['invert_columns'] ?? false) {
                $source['foreign_column'] = $localColumn;
                $source['column']         = $foreignColumn;
            }

            $this->sources[$name] = $source; // todo: SourceConfig?
        }
    }

    protected function setModules(): void
    {
        $modules = [
            'dashboard' => [
                'sort' => [],
            ],
            'detail'    => [
                'enabled' => true,
                'params'  => ['slug'], // fixme: architecture prevents configuration
                'public'  => true, // todo
            ],
            'charts'    => [
                'enabled' => false,
                'public'  => true, // todo
            ],
        ];

        $modules = $this->prepareConfig([], $modules, 'default/modules.yaml');
        if ($this->raw['modules'] ?? []) {
            $modules = array_replace_recursive($modules, $this->raw['modules']);
        }
        $modules['form'] = ['enabled' => true]; // fixme: this shouldn't be necessary

        $this->modules = $modules;
    }

    protected function prepareFields(ConfigStore $configStore): void
    {
        foreach ($this->raw['fields'] as $id => $config) {
            $this->fields[$id] = new Field($this->appId, $id, $config ?? [], $configStore, $this);
        }
    }

    protected function prepareFrontend(ConfigStore $configStore): void
    {
        $this->meta = [
            'title'               => $this->raw['label'] ?? Property::displayLabel($this->raw['name'], 'title'),
            'exposes'             => $this->raw['meta']['exposes'] ?? null, // <= slugs output
            'icon'                => $this->raw['meta']['icon'] ?? null,
            'sort'                => $this->raw['meta']['sort'] ?? null,
            'slug'                => $this->raw['meta']['slug'] ?? null,
        ];

        foreach ($this->fields as $field) {
            if ($field->getConstraint()) {
                $this->config['constraints'][$field->getConstraint()][] = $field->getId();
            }
        }

        if ($this->meta['exposes'] === null) {
            foreach ($this->fields as $field) {
                if ($field->isVisible(null)) {
                    $this->meta['exposes'] = $field->getId();
                    break;
                }
            }
        }

        // add slug field
        $slugConfig = [
            'dashboard' => false,
            'detail'    => false,
            'public'    => false,
            'type'      => 'text',
            'length'    => 100,
            'pointer'   => [
                'fields' => [],
                'type'   => 'slug',
            ],
        ];
        if ($this->meta['slug'] ?? null) {
            $slugConfig['pointer']['fields'] = (array)$this->meta['slug'];
            $this->fields['_slug'] = new Field($this->appId, '_slug', $slugConfig, $configStore);
        } else {
            if ($this->meta['exposes'] != 'id') {
                $slugConfig['pointer']['fields'] = (array)$this->meta['exposes'];
                $this->fields['_slug'] = new Field($this->appId, '_slug', $slugConfig, $configStore);
            }
        }

        $sortKeys = $this->getSortConfiguration();
        foreach ($sortKeys as $key => $dir) {
            if (is_int($key)) {
                $key = $dir;
                $dir = 'asc';
            }

            $this->sort[$this->getSchemaColumn($key)] = $dir;
        }
    }

    /**
     * Get sorting configuration
     *
     * @return array
     */
    private function getSortConfiguration(): array
    {
        if (!empty($this->raw['sort'])) {
            return $this->raw['sort'];
        } elseif (!empty($this->meta['sort'])) {
            return $this->meta['sort'];
        } elseif (!empty($this->modules['dashboard']['sort'])) {
            return $this->modules['dashboard']['sort'];
        }
        return (array)$this->meta['exposes'];
    }

    private function getSchemaColumn(string $column): string
    {
        if (isset($this->fields[$column]) && $this->fields[$column]->getSchema(true)) {
            return $this->fields[$column]->getSchema(true)['column'];
        }

        return $column;
    }

    private function prepareConfig(array $config, array $defaults = [], ?string $yamlConfig = null): array
    {
        if ($yamlConfig) {
            try {
                $userDefault = $this->readYamlFile($yamlConfig);
                $defaults    = array_replace($defaults, $userDefault);
            } catch (NoConfigurationException) {
                // normal operation
            }
        }

        return array_replace($defaults, $config);
    }
}