<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Core\DataAccess\PermissionContext;

/**
 * Campos ui_json para formulario parcial según aspectos elegidos (catálogo data-access-config).
 */
final class EditSparseFieldBuilder
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $authorization;
    private EditCatalogFormFieldBuilder $catalogFields;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null,
        ?EditCatalogFormFieldBuilder $catalogFields = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
        $this->catalogFields = $catalogFields ?? new EditCatalogFormFieldBuilder($this->catalog);
    }

    /**
     * @param list<string> $aspectIds
     * @param array<string, array<string, string>> $baseline
     * @param array<string, mixed> $params
     * @param array<string, int|string> $subjectContext
     * @return array{
     *   fields: list<array<string, mixed>>,
     *   aspect_blocks: list<array<string, mixed>>,
     *   open_ui: list<array<string, mixed>>,
     *   aspect_ids: list<string>
     * }
     */
    public function build(
        string $surfaceId,
        array $aspectIds,
        array $baseline,
        array $params,
        PermissionContext $ctx,
        array $subjectContext = []
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
        $aspectBlocks = [];
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

            $kind = trim((string) ($def['kind'] ?? 'field_group'));
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

            if ($kind === 'scalar_group') {
                $kind = 'field_group';
            }

            if ($kind !== 'field_group') {
                continue;
            }

            $prefill = $baseline[$aspectId] ?? [];
            if (!is_array($prefill)) {
                $prefill = [];
            }

            $aspectFields = $this->catalogFields->buildUiFieldsForAspect(
                $aspectId,
                $def,
                $prefill,
                $subjectContext
            );

            if ($aspectFields === []) {
                continue;
            }

            $submitHandler = trim((string) ($def['submit_handler'] ?? ''));
            if ($submitHandler !== '') {
                $aspectBlocks[] = [
                    'aspect_id' => $aspectId,
                    'title' => trim((string) ($def['label'] ?? $aspectId)) ?: $aspectId,
                    'fields' => $aspectFields,
                    'submit_handler' => $submitHandler,
                ];
            } else {
                foreach ($aspectFields as $field) {
                    $fields[] = $field;
                }
            }

            $accepted[] = $aspectId;
        }

        if ($accepted === []) {
            throw new \InvalidArgumentException('Elegí al menos un dato editable.');
        }

        return [
            'fields' => $fields,
            'aspect_blocks' => $aspectBlocks,
            'open_ui' => $openUi,
            'aspect_ids' => $accepted,
        ];
    }
}
