<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Resuelve columnas proyectables según grants READ del usuario.
 */
final class QueryProjectionBuilder
{
    /** @var AttributeGroupCatalog */
    private $catalog;

    /** @var AttributePermissionEvaluator */
    private $permissions;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?AttributePermissionEvaluator $permissions = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->permissions = $permissions ?? new AttributePermissionEvaluator();
    }

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
            if (!$this->permissions->can($ctx, $group, QueryOperation::READ)) {
                continue;
            }
            $out[] = [
                'output_key' => $outputKey,
                'sql_expression' => $column,
                'entity_group' => $group,
            ];
        }

        if ($out === []) {
            throw new \InvalidArgumentException('No tiene permiso de lectura sobre ningún campo del listado.');
        }

        return $out;
    }
}
