<?php

namespace App\System\Configuration;

use App\System\Application\Field;
use App\System\Application\Property;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ApplicationConfig
{
    use YamlReader;

    /** @var string Application identifier */
    public $appId;
    /** @var \App\System\Configuration\ApplicationCategory */
    private $category;
    /** @var array Configuration */
    private $config = [];

    /** @var Field[] */
    public $fields  = [];
    public $sort    = [];
    public $sources = [];
    public $modules = [];
    public $meta    = [];

    /** @var array Raw config */
    private $raw;

    public function __construct(ContainerInterface $container, ConfigStore $configStore, array $configuration, string $appId)
    {
        $this->basePath = $container->getParameter('kernel.project_dir') . '/config/';

        $this->appId = $appId;
        $this->raw = $configuration + ['appId' => $appId, 'name' => $appId];

        $this->setSources();
        $this->setModules();
        $this->prepareFields($configStore);
        $this->prepareFrontend($configStore);
        $this->setCategory($configStore);
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

    private function setCategory(ConfigStore $configStore)
    {
        $this->category = $configStore->getCategoryConfig($this->raw['category'] ?? 'default');
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
    public function getRoutes(?string $locale = null)
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
     * @return array
     */
    public function getMeta(?string $attribute = null)
    {
        return $attribute ? ($this->meta[$attribute] ?? null) : $this->meta;
    }

    protected function setSources()
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

    protected function setModules()
    {
        $modules = [
            'detail' => [
                'enabled' => true,
                'params'  => ['slug'], // fixme: architecture prevents configuration
                'public'  => true, // todo
            ],
            'charts' => [
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

    protected function prepareFields(ConfigStore $configStore)
    {
        foreach ($this->raw['fields'] as $id => $config) {
            $this->fields[$id] = new Field($this->appId, $id, $config ?? [], $configStore, $this);
        }
    }

    protected function prepareFrontend(ConfigStore $configStore)
    {
        $this->meta = [
            'title'               => $this->raw['label'] ?? Property::displayLabel($this->raw['name'], 'title'),
            'verbose_name'        => 'entity.verbose', // fixme: remove
            'verbose_name_plural' => 'entity.verbose_plural', // fixme: remove
            'exposes'             => $this->raw['meta']['exposes'] ?? null, // <= slugs output
        ];

        /** @var Field $field */
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
        if ($this->meta['slug'] ?? null) {
            $this->fields['_slug'] = new Field($this->appId, '_slug', [
                'dashboard' => false,
                'detail'    => false,
                'public'    => false,
                'type'      => 'text',
                'length'    => 100,
                'pointer'   => [
                    'fields' => (array)$this->meta['slug'],
                    'type'   => 'slug',
                ],
            ], $configStore);
        } else if ($this->meta['exposes'] != 'id') {
            $this->fields['_slug'] = new Field($this->appId, '_slug', [
                'dashboard' => false,
                'detail'    => false,
                'public'    => false,
                'type'      => 'text',
                'length'    => 100,
                'pointer'   => [
                    'fields' => (array)$this->meta['exposes'],
                    'type'   => 'slug',
                ],
            ], $configStore);
        }

        $sortKeys = !empty($this->raw['sort']) ? $this->raw['sort'] : (array)$this->meta['exposes'];
        foreach ($sortKeys as $key => $dir) {
            if (is_int($key)) {
                $key = $dir;
                $dir = 'asc';
            }

            $this->sort[$this->getSchemaColumn($key)] = $dir;
        }
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
            } catch (NoConfigurationException $e) {
                // normal operation
            }
        }

        return array_replace($defaults, $config);
    }
}