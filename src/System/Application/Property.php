<?php

namespace App\System\Application;

class Property
{
    public static function schemaName(string $applicationIdentifier)
    {
        return 'app_' . preg_replace('/\W+/', '_', $applicationIdentifier);
    }

    public static function foreignKey(string $applicationIdentifier)
    {
        return preg_replace('/[\-\/\s]+/', '_', $applicationIdentifier) . '_id';
    }

    public static function exposedColumn(array $applicationConfig)
    {
        return $applicationConfig['meta']['exposes'];
    }

    public static function displayLabel(string $input, string $type = 'label')
    {
        $label = strtolower(preg_replace('/\s+/', '_', $input));
        if (strpos($label, $type . '.') !== 0) {
            $label = $type . '.' . $label;
        }

        return $label;
    }

    public static function sourceKey(string $sourceId)
    {
        return explode('.', $sourceId)[0];
    }
}