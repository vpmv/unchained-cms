<?php

namespace App\System\Configuration;

use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Yaml\Yaml;

trait YamlReader
{
    protected string $basePath;

    /**
     * @param string $path
     *
     * @return mixed
     * @throws \Symfony\Component\Routing\Exception\NoConfigurationException
     */
    public function readYamlFile(string $path)
    {
        if (is_file($path)) {
            $filePath = $path;
        } else {
            $filePath = $this->basePath . $path;
        }
        if (!is_file($filePath)) {
            throw new NoConfigurationException('Could not find configuration file ' . $path);
        }

        $contents = file_get_contents($filePath);
        $output   = Yaml::parse($contents);

        return $output;
    }

}