<?php

namespace common\components\Platform\Core\DataAccess\Edit;

use common\components\Platform\Core\DataAccess\AttributeGroupCatalog;
use common\components\Platform\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Platform\Core\DataAccess\PermissionContext;

/**
 * Lista atributos editables (no grupos/aspectos) para el paso «elegí el dato».
 */
final class EditSparseFieldPicker
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $authorization;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{id: string, name: string, meta: array{aspect_id: string, fields: string}}>
     */
    public function listItems(string $surfaceId, PermissionContext $ctx, array $params = []): array
    {
        $surfaceId = trim($surfaceId);
        if ($surfaceId === '') {
            return [];
        }

        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            return [];
        }

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        $out = [];
        foreach ($aspects as $aspectId => $def) {
            if (!is_string($aspectId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
                continue;
            }

            foreach ($this->fieldNamesForAspect($aspectId, $def) as $fieldName) {
                $label = $this->labelForField($def, $fieldName);
                $out[] = [
                    'id' => $aspectId . ':' . $fieldName,
                    'name' => $label,
                    'meta' => [
                        'aspect_id' => $aspectId,
                        'fields' => $fieldName,
                    ],
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @return list<string>
     */
    private function fieldNamesForAspect(string $aspectId, array $aspectDef): array
    {
        $explicit = $aspectDef['fields'] ?? null;
        if (is_array($explicit) && $explicit !== []) {
            $names = [];
            foreach ($explicit as $name) {
                $key = trim((string) $name);
                if ($key !== '' && $key !== 'weekly_scheduler_widget') {
                    $names[] = $key;
                }
            }
            if (in_array('weekly_scheduler_widget', $explicit, true)
                || $this->aspectHasSchedulerWidget($aspectDef, $explicit)) {
                $names[] = 'weekly_scheduler_widget';
            }

            return array_values(array_unique($names));
        }

        $kind = trim((string) ($aspectDef['kind'] ?? 'field_group'));
        if ($kind === 'open_ui') {
            return [];
        }

        $groupKey = trim((string) ($aspectDef['attribute_group'] ?? ''));
        if ($groupKey === '') {
            return [];
        }

        $definitions = $this->catalog->getEntityGroupFieldDefinitions($groupKey);
        $names = [];
        foreach ($definitions as $name => $fieldDef) {
            if (!is_string($name) || !is_array($fieldDef)) {
                continue;
            }
            if (($fieldDef['form'] ?? true) === false) {
                continue;
            }
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $aspectDef
     * @param list<string> $explicit
     */
    private function aspectHasSchedulerWidget(array $aspectDef, array $explicit): bool
    {
        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $day) {
            if (in_array($day, $explicit, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $aspectDef
     */
    private function labelForField(array $aspectDef, string $fieldName): string
    {
        if ($fieldName === 'weekly_scheduler_widget') {
            return 'Horarios por día (grilla de turnos)';
        }

        $groupKey = trim((string) ($aspectDef['attribute_group'] ?? ''));
        if ($groupKey !== '') {
            $definitions = $this->catalog->getEntityGroupFieldDefinitions($groupKey);
            $def = $definitions[$fieldName] ?? null;
            if (is_array($def)) {
                $label = trim((string) ($def['label'] ?? ''));
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $fieldName));
    }
}
