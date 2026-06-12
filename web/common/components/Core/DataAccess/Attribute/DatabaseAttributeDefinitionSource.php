<?php

namespace common\components\Core\DataAccess\Attribute;

use common\models\DataAccess\DataAccessAttributeField;

/**
 * Definiciones de campos por grupo desde BD ({@see DataAccessAttributeField}).
 */
final class DatabaseAttributeDefinitionSource
{
    /** @var array<string, array<string, array<string, mixed>>|null> */
    private static $cacheByGroup;

    public static function clearCache(): void
    {
        self::$cacheByGroup = null;
    }

    public static function groupExists(string $entityGroupKey): bool
    {
        $entityGroupKey = trim($entityGroupKey);
        if ($entityGroupKey === '') {
            return false;
        }

        return self::getFieldDefinitions($entityGroupKey) !== [];
    }

    /**
     * @return array<string, string>
     */
    public static function listGroupOptions(): array
    {
        try {
            $rows = DataAccessAttributeField::find()
                ->select(['entity_group_key'])
                ->where(['active' => 1])
                ->distinct()
                ->orderBy(['entity_group_key' => SORT_ASC])
                ->column();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $groupKey) {
            $key = trim((string) $groupKey);
            if ($key === '') {
                continue;
            }
            $attrs = implode(', ', array_keys(self::getFieldDefinitions($key)));
            $out[$key] = $attrs !== '' ? ($key . ' (' . $attrs . ')') : $key;
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getFieldDefinitions(string $entityGroupKey): array
    {
        $entityGroupKey = trim($entityGroupKey);
        if ($entityGroupKey === '') {
            return [];
        }

        if (self::$cacheByGroup === null) {
            self::$cacheByGroup = self::loadAllActive();
        }

        return self::$cacheByGroup[$entityGroupKey] ?? [];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function loadAllActive(): array
    {
        try {
            $rows = DataAccessAttributeField::find()
                ->where(['active' => 1])
                ->orderBy(['entity_group_key' => SORT_ASC, 'sort_order' => SORT_ASC, 'field_name' => SORT_ASC])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DataAccessAttributeField) {
                continue;
            }
            $groupKey = trim((string) $row->entity_group_key);
            $fieldName = trim((string) $row->field_name);
            if ($groupKey === '' || $fieldName === '') {
                continue;
            }
            if (!isset($out[$groupKey])) {
                $out[$groupKey] = [];
            }
            $out[$groupKey][$fieldName] = $row->toCatalogDefinition();
        }

        return $out;
    }
}
