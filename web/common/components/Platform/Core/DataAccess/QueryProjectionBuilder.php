<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Resuelve columnas proyectables de un listado (autorización ya aplicada a nivel métrica/intent).
 */
final class QueryProjectionBuilder
{
    /**
     * @param array<string, mixed> $rowsDef bloque output.rows del plan
     * @return list<array{output_key: string, sql_expression: string, entity_group: string}>
     */
    public function resolveReadableFields(PermissionContext $ctx, array $rowsDef): array
    {
        $fields = isset($rowsDef['fields']) && is_array($rowsDef['fields']) ? $rowsDef['fields'] : [];
        $out = [];

        foreach ($fields as $outputKey => $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }
            $outputKey = trim((string) $outputKey);
            $group = trim((string) ($fieldDef['group'] ?? ''));
            $column = trim((string) ($fieldDef['column'] ?? ''));
            if ($outputKey === '' || $group === '' || $column === '') {
                continue;
            }
            $out[] = [
                'output_key' => $outputKey,
                'sql_expression' => $column,
                'entity_group' => $group,
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('La métrica no declara campos legibles para el listado.');
        }

        return $out;
    }
}
