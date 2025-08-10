<?php

namespace App\System\Application;

class Property
{
    public static function domain(string $applicationIdentifier, string $prefix = 'app_'): string
    {
        return $prefix . preg_replace('/\W+/', '_', $applicationIdentifier);
    }

    public static function schemaName(string $applicationIdentifier): string
    {
        return self::domain($applicationIdentifier);
    }

    public static function foreignKey(string $applicationIdentifier): string
    {
        return preg_replace('/[\-\/\s]+/', '_', $applicationIdentifier) . '_id';
    }

    public static function exposedColumn(array $applicationConfig): string|array
    {
        return $applicationConfig['meta']['exposes'];
    }

    public static function displayLabel(string $input, string $type = 'label'): string
    {
        $label = strtolower(preg_replace('/\s+/', '_', $input));
        if (!str_starts_with($label, $type . '.')) {
            $label = $type . '.' . $label;
        }

        return $label;
    }

    public static function sourceKey(string $sourceId): string
    {
        return explode('.', $sourceId)[0];
    }
}