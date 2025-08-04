<?php

namespace App\System\Configuration;

use App\System\Constructs\Cacheable;

class ConfigStoreBase extends Cacheable
{
    use YamlReader;

    protected const BASE_KEYS = [
        'category',
        'order',
        'public',
        'routes',
    ];

    protected $systemConfig = [];

    public function __construct(string $projectDir)
    {
        parent::__construct('config.');
        $this->basePath = $projectDir . '/user/config/';
    }

    /**
     * @param string      $name
     * @param null|string $attribute
     * @param null|mixed  $default
     *
     * @return mixed
     */
    public function readSystemConfig(string $name, ?string $attribute = null, mixed $default = null): mixed
    {
        $this->systemConfig[$name] = $this->remember('system.' . $name, function () use ($name) {
            return $this->readYamlFile($name . '.yaml');
        });

        if ($attribute) {
            return $this->systemConfig[$name][$attribute] ?? $default;
        }

        return $this->systemConfig[$name] ?? $default;
    }

    public function getUnchainedConfig(): UnchainedConfig
    {
        $config = $this->readSystemConfig('config', 'config');
        return $this->remember('unchained.config', function () use ($config) {
            return new UnchainedConfig(...$config);
        });
    }
}