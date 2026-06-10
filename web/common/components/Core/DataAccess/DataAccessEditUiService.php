<?php

namespace common\components\Core\DataAccess;

use common\components\Ui\UiScreenService;
use yii\web\ForbiddenHttpException;

/**
 * UI JSON para edición dispersa ({@see /api/editar}).
 */
final class DataAccessEditUiService
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $authorization;
    private DataAccessUiService $dataAccessUi;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null,
        ?DataAccessUiService $dataAccessUi = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
        $this->dataAccessUi = $dataAccessUi ?? new DataAccessUiService();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function render(array $params, ?PermissionContext $ctx = null): array
    {
        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        if (!$this->authorization->userHasAnyEditableSurface($ctx, $params)) {
            throw new ForbiddenHttpException('No tenés permiso para modificar datos en el sistema.');
        }

        $step = trim((string) ($params['step'] ?? ''));
        $surfaceId = trim((string) ($params['surface_id'] ?? $params['edit_surface_id'] ?? ''));

        if ($step === 'subjects' || $step === 'subject') {
            return $this->renderSubjectList($params, $ctx, $surfaceId);
        }

        if ($surfaceId !== '') {
            if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
                throw new ForbiddenHttpException('No tenés permiso para editar esa categoría de datos.');
            }

            return $this->renderAspectPicker($surfaceId, $params, $ctx);
        }

        return $this->renderSurfacePicker($ctx, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderSurfacePicker(PermissionContext $ctx, array $params): array
    {
        $options = [];
        foreach ($this->catalog->listEditSurfacesForDisplay() as $surfaceId => $def) {
            if (!is_string($surfaceId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
                continue;
            }
            $options[] = [
                'value' => $surfaceId,
                'label' => trim((string) ($def['label'] ?? $surfaceId)) ?: $surfaceId,
            ];
        }

        if ($options === []) {
            throw new ForbiddenHttpException('No tenés permiso para modificar datos en el sistema.');
        }

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => '¿Qué querés editar?',
            'message' => 'Elegí el tipo de datos que necesitás modificar.',
            'step' => 'surfaces',
            'surface_options' => $options,
        ], null);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'surfaces',
            'surfaces' => $options,
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderAspectPicker(string $surfaceId, array $params, PermissionContext $ctx): array
    {
        $surface = $this->catalog->getEditSurface($surfaceId);
        $label = is_array($surface)
            ? (trim((string) ($surface['label'] ?? $surfaceId)) ?: $surfaceId)
            : $surfaceId;

        $aspects = $this->authorization->listEditableAspects($ctx, $surfaceId, $params);
        if ($aspects === []) {
            throw new ForbiddenHttpException('No tenés permiso para modificar ningún aspecto de ' . $label . '.');
        }

        $resolver = is_array($surface) ? ($surface['subject_resolver'] ?? []) : [];
        $metricId = is_array($resolver) ? trim((string) ($resolver['metric_id'] ?? '')) : '';
        $needsSubject = trim((string) ($params['id_persona'] ?? '')) === ''
            && trim((string) ($params['id_profesional_efector_servicio'] ?? '')) === '';

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => 'Editar: ' . $label,
            'message' => $needsSubject
                ? 'Primero elegí el registro; después marcá qué aspectos querés modificar.'
                : 'Marcá los aspectos que querés modificar.',
            'step' => 'aspects',
            'surface_id' => $surfaceId,
            'aspect_options' => $aspects,
            'subject_list_url' => $needsSubject && $metricId !== ''
                ? '/api/v1/editar?step=subjects&surface_id=' . rawurlencode($surfaceId)
                : null,
        ], null);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'aspects',
            'surface_id' => $surfaceId,
            'aspects' => $aspects,
            'needs_subject' => $needsSubject,
            'next_subject_step' => $needsSubject && $metricId !== ''
                ? ['step' => 'subjects', 'surface_id' => $surfaceId, 'metric_id' => $metricId]
                : null,
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderSubjectList(array $params, PermissionContext $ctx, string $surfaceId): array
    {
        if ($surfaceId === '') {
            throw new \InvalidArgumentException('surface_id es requerido para elegir sujeto.');
        }
        if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
            throw new ForbiddenHttpException('No tenés permiso para editar esa categoría de datos.');
        }

        $surface = $this->catalog->getEditSurface($surfaceId);
        $resolver = is_array($surface) ? ($surface['subject_resolver'] ?? []) : [];
        if (!is_array($resolver)) {
            throw new \InvalidArgumentException('La superficie no define subject_resolver.');
        }

        $metricId = trim((string) ($resolver['metric_id'] ?? ''));
        if ($metricId === '') {
            throw new \InvalidArgumentException('subject_resolver.metric_id es requerido.');
        }

        $listParams = $params;
        $listParams['metric_id'] = $metricId;

        $listOut = $this->dataAccessUi->renderListar($listParams, $ctx);
        $listOut['data']['edit_context'] = [
            'step' => 'subjects',
            'surface_id' => $surfaceId,
            'selection_param' => trim((string) ($resolver['selection_param'] ?? 'id_persona')) ?: 'id_persona',
            'pes_param' => trim((string) ($resolver['pes_param'] ?? 'id_profesional_efector_servicio'))
                ?: 'id_profesional_efector_servicio',
            'next_step' => 'aspects',
        ];

        if (isset($listOut['ui_meta']) && is_array($listOut['ui_meta'])) {
            $listOut['ui_meta']['edit_sparse'] = $listOut['data']['edit_context'];
        } else {
            $listOut['ui_meta'] = ['edit_sparse' => $listOut['data']['edit_context']];
        }

        return $listOut;
    }
}
