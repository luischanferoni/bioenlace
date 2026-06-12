<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Core\DataAccess\PermissionContext;

/**
 * Campos ui_json para formulario parcial según aspectos elegidos.
 */
final class EditSparseFieldBuilder
{
    private const FIELD_LABELS = [
        'nombre' => 'Nombre',
        'apellido' => 'Apellido',
        'otro_nombre' => 'Otro nombre',
        'otro_apellido' => 'Otro apellido',
    ];

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
     * @param list<string> $aspectIds
     * @param array<string, array<string, string>> $baseline
     * @param array<string, mixed> $params
     * @return array{
     *   fields: list<array<string, mixed>>,
     *   open_ui: list<array<string, mixed>>,
     *   aspect_ids: list<string>
     * }
     */
    public function build(
        string $surfaceId,
        array $aspectIds,
        array $baseline,
        array $params,
        PermissionContext $ctx
    ): array {
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            throw new \InvalidArgumentException('Superficie de edición desconocida.');
        }

        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            $aspects = [];
        }

        $fields = [];
        $openUi = [];
        $accepted = [];

        foreach ($aspectIds as $aspectId) {
            if (!$this->authorization->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
                continue;
            }
            $def = $aspects[$aspectId] ?? null;
            if (!is_array($def)) {
                continue;
            }

            $kind = trim((string) ($def['kind'] ?? 'scalar_group'));
            if ($kind === 'open_ui') {
                $openUi[] = [
                    'aspect_id' => $aspectId,
                    'label' => trim((string) ($def['label'] ?? $aspectId)) ?: $aspectId,
                    'ui_action' => trim((string) ($def['ui_action'] ?? '')),
                    'requires_params' => is_array($def['requires_params'] ?? null) ? $def['requires_params'] : [],
                ];
                $accepted[] = $aspectId;
                continue;
            }

            if ($kind !== 'scalar_group') {
                continue;
            }

            $aspectFields = $def['fields'] ?? [];
            if (!is_array($aspectFields)) {
                $aspectFields = [];
            }
            $prefill = $baseline[$aspectId] ?? [];

            foreach ($aspectFields as $fieldName) {
                $key = trim((string) $fieldName);
                if ($key === '') {
                    continue;
                }
                $fields[] = [
                    'name' => $key,
                    'type' => 'text',
                    'label' => self::FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                    'value' => (string) ($prefill[$key] ?? ''),
                    'include_in_submit' => true,
                    'meta' => [
                        'aspect_id' => $aspectId,
                        'attribute_group' => trim((string) ($def['attribute_group'] ?? '')),
                    ],
                ];
            }
            $accepted[] = $aspectId;
        }

        if ($accepted === []) {
            throw new \InvalidArgumentException('Elegí al menos un dato editable.');
        }

        return [
            'fields' => $fields,
            'open_ui' => $openUi,
            'aspect_ids' => $accepted,
        ];
    }
}
