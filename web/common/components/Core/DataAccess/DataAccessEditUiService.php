<?php

namespace common\components\Core\DataAccess;

use common\components\Core\DataAccess\Edit\EditMutationAuthorizationService;
use common\components\Core\DataAccess\Edit\EditSparseAspectIds;
use common\components\Core\DataAccess\Edit\EditSparseConfirmPresenter;
use common\components\Core\DataAccess\Edit\EditSparseFieldBuilder;
use common\components\Core\DataAccess\Edit\EditSparseSubjectLoader;
use common\components\Core\DataAccess\Edit\EditMutationResult;
use common\components\Core\DataAccess\Edit\MutationExecutor;
use common\components\Core\DataAccess\Edit\OpenUiEditMutationDelegate;
use common\components\Organization\Service\ProfesionalEfectorServicio\AgendaConfigUiFlowService;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaUiService;
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
    private EditSparseSubjectLoader $subjectLoader;
    private EditSparseFieldBuilder $fieldBuilder;
    private EditSparseConfirmPresenter $confirmPresenter;
    private MutationExecutor $mutationExecutor;
    private EditMutationAuthorizationService $mutationAuth;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null,
        ?DataAccessUiService $dataAccessUi = null,
        ?EditSparseSubjectLoader $subjectLoader = null,
        ?EditSparseFieldBuilder $fieldBuilder = null,
        ?EditSparseConfirmPresenter $confirmPresenter = null,
        ?MutationExecutor $mutationExecutor = null,
        ?EditMutationAuthorizationService $mutationAuth = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
        $this->dataAccessUi = $dataAccessUi ?? new DataAccessUiService();
        $this->subjectLoader = $subjectLoader ?? new EditSparseSubjectLoader($this->catalog, $this->authorization);
        $this->fieldBuilder = $fieldBuilder ?? new EditSparseFieldBuilder($this->catalog, $this->authorization);
        $this->confirmPresenter = $confirmPresenter ?? new EditSparseConfirmPresenter();
        $this->mutationExecutor = $mutationExecutor ?? new MutationExecutor($this->catalog, $this->authorization);
        $this->mutationAuth = $mutationAuth ?? new EditMutationAuthorizationService($this->catalog);
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

        if ($step === 'apply') {
            return $this->renderApply($params, $ctx, $surfaceId);
        }

        if ($step === 'confirm') {
            return $this->renderConfirm($params, $ctx, $surfaceId);
        }

        if ($step === 'form') {
            return $this->renderForm($params, $ctx, $surfaceId);
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

        if (count($options) === 1) {
            $onlyId = (string) $options[0]['value'];
            $params['surface_id'] = $onlyId;

            return $this->renderAspectPicker($onlyId, $params, $ctx);
        }

        $listItems = [];
        foreach ($options as $opt) {
            $listItems[] = [
                'id' => (string) ($opt['value'] ?? ''),
                'name' => (string) ($opt['label'] ?? ''),
            ];
        }

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => '¿Qué querés editar?',
            'message' => 'Elegí el tipo de datos que necesitás modificar.',
            'step' => 'surfaces',
            'surface_options' => $options,
        ], null);

        $out = $this->appendBlocks($out, [[
            'kind' => 'list',
            'id' => 'editar_superficies',
            'title' => 'Tipo de datos',
            'items' => $listItems,
            'presentation' => [
                'tile' => 'medium',
                'shape' => 'wide',
            ],
            'selection' => [
                'mode' => 'single',
                'requires_confirmation' => false,
            ],
            'draft_field' => 'surface_id',
        ]]);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'surfaces',
            'surfaces' => $options,
            'edit_context' => [
                'step' => 'surfaces',
                'next_step' => 'aspects',
                'pes_param' => 'surface_id',
            ],
        ];
        if (isset($out['ui_meta']) && is_array($out['ui_meta'])) {
            $out['ui_meta']['edit_sparse'] = $out['data']['edit_context'];
        } else {
            $out['ui_meta'] = ['edit_sparse' => $out['data']['edit_context']];
        }

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
            throw new ForbiddenHttpException('No tenés permiso para modificar ningún dato de ' . $label . '.');
        }

        $resolver = is_array($surface) ? ($surface['subject_resolver'] ?? []) : [];
        $metricId = is_array($resolver) ? trim((string) ($resolver['metric_id'] ?? '')) : '';
        $needsSubject = trim((string) ($params['id_persona'] ?? '')) === ''
            && trim((string) ($params['id_profesional_efector_servicio'] ?? '')) === '';

        if ($needsSubject && $metricId !== '') {
            return $this->renderSubjectList($params, $ctx, $surfaceId);
        }

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => 'Editar: ' . $label,
            'message' => 'Elegí qué dato querés modificar.',
            'step' => 'aspects',
            'surface_id' => $surfaceId,
            'aspect_options' => $aspects,
        ], null);

        $listItems = [];
        foreach ($aspects as $aspect) {
            $listItems[] = [
                'id' => (string) ($aspect['id'] ?? ''),
                'name' => (string) ($aspect['label'] ?? ''),
            ];
        }

        $out = $this->appendBlocks($out, [[
            'kind' => 'list',
            'id' => 'editar_datos',
            'title' => 'Datos',
            'items' => $listItems,
            'presentation' => [
                'tile' => 'medium',
                'shape' => 'wide',
            ],
            'selection' => [
                'mode' => 'single',
                'requires_confirmation' => false,
            ],
            'draft_field' => 'aspect_ids',
        ]]);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'aspects',
            'surface_id' => $surfaceId,
            'aspects' => $aspects,
            'edit_context' => [
                'step' => 'aspects',
                'surface_id' => $surfaceId,
                'next_step' => 'form',
                'pes_param' => 'aspect_ids',
            ],
        ];

        if (isset($out['ui_meta']) && is_array($out['ui_meta'])) {
            $out['ui_meta']['edit_sparse'] = $out['data']['edit_context'];
        } else {
            $out['ui_meta'] = ['edit_sparse' => $out['data']['edit_context']];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderForm(array $params, PermissionContext $ctx, string $surfaceId): array
    {
        if ($surfaceId === '') {
            throw new \InvalidArgumentException('surface_id es requerido.');
        }

        $aspectIds = EditSparseAspectIds::fromParams($params);
        if ($aspectIds === []) {
            throw new \InvalidArgumentException('aspect_ids es requerido (elegí al menos un dato).');
        }

        $subject = $this->subjectLoader->load($surfaceId, $params, $ctx);
        $built = $this->fieldBuilder->build(
            $surfaceId,
            $aspectIds,
            $subject['baseline'],
            $params,
            $ctx
        );

        $scalarFields = $built['fields'];
        $blocks = $this->buildAspectFieldBlocks(
            $built['aspect_blocks'],
            $subject['context'],
            $surfaceId
        );
        try {
            $blocks = array_merge(
                $blocks,
                $this->embedOpenUiFieldBlocks(
                    $built['open_ui'],
                    $subject['context'],
                    array_merge($params, ['surface_id' => $surfaceId])
                )
            );
        } catch (\yii\web\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        if ($scalarFields !== []) {
            $blocks[] = [
                'kind' => 'fields',
                'id' => 'editar_formulario',
                'title' => 'Datos a modificar — ' . $subject['label'],
                'fields' => array_merge(
                    $scalarFields,
                    $this->contextHiddenFields($subject['context'], 'confirm', [
                        'aspect_ids' => implode(',', $built['aspect_ids']),
                    ])
                ),
            ];
        }

        if ($blocks === []) {
            throw new \RuntimeException('No hay datos editables para el dato seleccionado.');
        }

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => 'Editar: ' . $subject['label'],
            'message' => '',
            'step' => 'form',
            'surface_id' => $surfaceId,
        ], null);
        $out = $this->appendBlocks($out, $blocks);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'form',
            'surface_id' => $surfaceId,
            'aspect_ids' => $built['aspect_ids'],
            'subject' => $subject['context'],
            'baseline' => $subject['baseline'],
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderConfirm(array $params, PermissionContext $ctx, string $surfaceId): array
    {
        if ($surfaceId === '') {
            throw new \InvalidArgumentException('surface_id es requerido.');
        }

        $aspectIds = EditSparseAspectIds::fromParams($params);
        if ($aspectIds === []) {
            throw new \InvalidArgumentException('aspect_ids es requerido.');
        }

        $subject = $this->subjectLoader->load($surfaceId, $params, $ctx);
        $built = $this->fieldBuilder->build(
            $surfaceId,
            $aspectIds,
            $subject['baseline'],
            $params,
            $ctx
        );

        $proposed = $this->extractProposedValues($surfaceId, $params, $subject['baseline'], $built['aspect_ids']);
        $diff = $this->confirmPresenter->buildDiff($subject['baseline'], $proposed, $built['aspect_ids']);
        $previewText = $this->confirmPresenter->formatPreviewText(
            $subject['label'],
            $diff,
            $built['open_ui']
        );

        $hidden = $this->contextHiddenFields($subject['context'], 'apply', [
            'aspect_ids' => implode(',', $built['aspect_ids']),
        ]);
        foreach ($proposed as $field => $value) {
            $hidden[] = [
                'name' => $field,
                'type' => 'hidden',
                'value' => $value,
                'include_in_submit' => true,
            ];
        }

        $out = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => 'Confirmar cambios',
            'message' => $previewText,
            'step' => 'confirm',
            'surface_id' => $surfaceId,
        ], null);
        $out = $this->appendBlocks($out, [[
            'kind' => 'fields',
            'id' => 'editar_confirmar',
            'title' => 'Confirmación',
            'fields' => $hidden,
        ]]);

        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'step' => 'confirm',
            'surface_id' => $surfaceId,
            'aspect_ids' => $built['aspect_ids'],
            'subject' => $subject['context'],
            'changes' => $diff['changes'],
            'has_changes' => $diff['has_changes'],
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function renderApply(array $params, PermissionContext $ctx, string $surfaceId): array
    {
        if ($surfaceId === '') {
            throw new \InvalidArgumentException('surface_id es requerido.');
        }

        $aspectIds = EditSparseAspectIds::fromParams($params);
        if ($aspectIds === []) {
            throw new \InvalidArgumentException('aspect_ids es requerido.');
        }

        $subject = $this->subjectLoader->load($surfaceId, $params, $ctx);
        $built = $this->fieldBuilder->build(
            $surfaceId,
            $aspectIds,
            $subject['baseline'],
            $params,
            $ctx
        );
        $proposed = $this->extractProposedValues($surfaceId, $params, $subject['baseline'], $built['aspect_ids']);

        $result = $this->mutationExecutor->apply(
            $surfaceId,
            $built['aspect_ids'],
            $subject['baseline'],
            $proposed,
            $subject['context'],
            $params,
            $ctx
        );

        $message = $this->buildApplySuccessMessage($result);

        $data = [
            'surface_id' => $surfaceId,
            'aspect_ids' => $built['aspect_ids'],
            'subject' => $result->subjectContext,
            'changes' => $result->appliedChanges,
            'message' => $message,
        ];
        if ($result->hasOpenUiActions()) {
            $data['open_ui'] = $result->openUiActions;
        }

        return [
            'kind' => 'ui_submit_result',
            'success' => true,
            'action_id' => 'data-access.editar',
            'data' => $data,
            'errors' => null,
        ];
    }

    private function buildApplySuccessMessage(EditMutationResult $result): string
    {
        if ($result->hasScalarChanges() && $result->hasOpenUiActions()) {
            return 'Los cambios se guardaron correctamente.';
        }
        if ($result->hasScalarChanges()) {
            return 'Los cambios se guardaron correctamente.';
        }

        return 'Los cambios se guardaron correctamente.';
    }

    /**
     * Bloques de formulario declarados en catálogo (field_group + submit_handler).
     *
     * @param list<array<string, mixed>> $aspectBlocks
     * @param array<string, int|string> $subjectContext
     * @return list<array<string, mixed>>
     */
    private function buildAspectFieldBlocks(array $aspectBlocks, array $subjectContext, string $surfaceId): array
    {
        $blocks = [];
        foreach ($aspectBlocks as $aspectBlock) {
            if (!is_array($aspectBlock)) {
                continue;
            }
            $aspectId = trim((string) ($aspectBlock['aspect_id'] ?? ''));
            if ($aspectId === '') {
                continue;
            }
            $fields = isset($aspectBlock['fields']) && is_array($aspectBlock['fields']) ? $aspectBlock['fields'] : [];
            if ($fields === []) {
                continue;
            }
            $applyHidden = $this->contextHiddenFields(
                array_merge($subjectContext, $surfaceId !== '' ? ['surface_id' => $surfaceId] : []),
                'apply',
                ['aspect_ids' => $aspectId]
            );
            $blocks[] = [
                'kind' => 'fields',
                'id' => 'editar_aspect_' . $aspectId,
                'title' => trim((string) ($aspectBlock['title'] ?? $aspectId)) ?: $aspectId,
                'fields' => array_merge($applyHidden, $fields),
                'submit_api' => [
                    'route' => '/api/v1/editar',
                    'method' => 'POST',
                ],
            ];
        }

        return $blocks;
    }

    /**
     * Incrusta formularios ui_json de aspectos open_ui (legacy) en el paso form.
     *
     * @param list<array<string, mixed>> $openUiAspects
     * @param array<string, int|string> $subjectContext
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function embedOpenUiFieldBlocks(array $openUiAspects, array $subjectContext, array $params): array
    {
        if ($openUiAspects === []) {
            return [];
        }

        $delegate = new OpenUiEditMutationDelegate();
        $blocks = [];

        foreach ($openUiAspects as $openUi) {
            if (!is_array($openUi)) {
                continue;
            }
            $aspectId = trim((string) ($openUi['aspect_id'] ?? ''));
            $uiAction = trim((string) ($openUi['ui_action'] ?? ''));
            if ($aspectId === '' || $uiAction === '') {
                continue;
            }

            $action = $delegate->buildAction($aspectId, [
                'ui_action' => $uiAction,
                'requires_params' => is_array($openUi['requires_params'] ?? null) ? $openUi['requires_params'] : [],
            ], $subjectContext);

            [$entity, $actionName] = $this->splitUiActionId($action['action_id']);
            $queryParams = array_merge($params, $action['params']);

            if ($action['action_id'] === 'profesional-agenda.configurar-agenda') {
                $idEfector = (int) ($subjectContext['id_efector'] ?? $params['id_efector'] ?? 0);
                $queryParams['ui_step'] = trim((string) ($queryParams['ui_step'] ?? AgendaConfigUiFlowService::STEP_DATOS));
                $nested = AgendaConfigUiFlowService::renderStep($idEfector, $queryParams);
            } else {
                $nested = UiScreenService::renderUiDefinition($entity, $actionName, $queryParams, null);
            }

            $aspectLabel = trim((string) ($openUi['label'] ?? $aspectId)) ?: $aspectId;

            foreach ($nested['blocks'] ?? [] as $block) {
                if (!is_array($block) || trim((string) ($block['kind'] ?? '')) !== 'fields') {
                    continue;
                }
                $block['id'] = 'editar_open_ui_' . $aspectId;
                $block['title'] = $aspectLabel;
                if ($action['action_id'] === 'profesional-agenda.configurar-agenda') {
                    $block['submit_api'] = [
                        'route' => '/api/v1/profesional-agenda/configurar-agenda',
                        'method' => 'POST',
                    ];
                } else {
                    $surfaceId = trim((string) ($params['surface_id'] ?? ''));
                    $applyHidden = $this->contextHiddenFields(
                        array_merge($subjectContext, $surfaceId !== '' ? ['surface_id' => $surfaceId] : []),
                        'apply',
                        ['aspect_ids' => $aspectId]
                    );
                    $existingFields = isset($block['fields']) && is_array($block['fields']) ? $block['fields'] : [];
                    $block['fields'] = array_merge($applyHidden, $existingFields);
                    $block['submit_api'] = [
                        'route' => '/api/v1/editar',
                        'method' => 'POST',
                    ];
                }
                $blocks[] = $block;
            }
        }

        if ($blocks === [] && $openUiAspects !== []) {
            throw new \RuntimeException('No se pudo cargar la UI de edición para el dato seleccionado.');
        }

        return $blocks;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUiActionId(string $actionId): array
    {
        $actionId = trim($actionId);
        $dot = strpos($actionId, '.');
        if ($dot === false) {
            throw new \InvalidArgumentException('ui_action inválido: ' . $actionId);
        }

        return [
            substr($actionId, 0, $dot),
            substr($actionId, $dot + 1),
        ];
    }

    private function httpRouteForUiAction(string $actionId): string
    {
        [$entity, $actionName] = $this->splitUiActionId($actionId);

        return '/api/v1/' . rawurlencode($entity) . '/' . rawurlencode($actionName);
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

        $surfaceLabel = is_array($surface)
            ? (trim((string) ($surface['label'] ?? $surfaceId)) ?: $surfaceId)
            : $surfaceId;
        $pesParam = trim((string) ($resolver['pes_param'] ?? 'id_profesional_efector_servicio'))
            ?: 'id_profesional_efector_servicio';
        $items = isset($listOut['data']['items']) && is_array($listOut['data']['items'])
            ? $listOut['data']['items']
            : [];

        $intro = UiScreenService::renderUiDefinition('data-access', 'editar', [
            'title' => 'Editar: ' . $surfaceLabel,
            'message' => $items === []
                ? 'No hay registros para editar en este efector.'
                : 'Elegí el registro que querés modificar.',
            'step' => 'subjects',
            'surface_id' => $surfaceId,
        ], null);

        $blocks = isset($intro['blocks']) && is_array($intro['blocks']) ? $intro['blocks'] : [];
        foreach ($listOut['blocks'] ?? [] as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (trim((string) ($block['kind'] ?? '')) === 'list') {
                $block = $this->patchListBlockForEditSubjectSelection($block, $pesParam);
            }
            $blocks[] = $block;
        }

        $listOut['blocks'] = $blocks;
        $listOut['title'] = 'Editar: ' . $surfaceLabel;
        $listOut['action_id'] = 'data-access.editar';
        $listOut['kind'] = 'ui_definition';
        $listOut['data']['edit_context'] = [
            'step' => 'subjects',
            'surface_id' => $surfaceId,
            'selection_param' => trim((string) ($resolver['selection_param'] ?? 'id_persona')) ?: 'id_persona',
            'pes_param' => $pesParam,
            'next_step' => 'aspects',
        ];
        $listOut['data']['step'] = 'subjects';
        $listOut['data']['surface_id'] = $surfaceId;

        if (isset($listOut['ui_meta']) && is_array($listOut['ui_meta'])) {
            $listOut['ui_meta']['edit_sparse'] = $listOut['data']['edit_context'];
        } else {
            $listOut['ui_meta'] = ['edit_sparse' => $listOut['data']['edit_context']];
        }

        return $listOut;
    }

    /**
     * Lista seleccionable (botones) en lugar de tabla de solo lectura.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function patchListBlockForEditSubjectSelection(array $block, string $pesParam): array
    {
        $presentation = isset($block['presentation']) && is_array($block['presentation'])
            ? $block['presentation']
            : [];
        unset($presentation['layout']);

        $block['presentation'] = array_merge($presentation, [
            'tile' => 'medium',
            'shape' => 'wide',
        ]);
        $block['selection'] = [
            'mode' => 'single',
            'requires_confirmation' => false,
        ];
        $block['draft_field'] = $pesParam;

        $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : [];
        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $servicio = trim((string) ($item['servicio_nombre'] ?? ''));
            if ($name !== '' && $servicio !== '') {
                $items[$idx]['name'] = $name . ' — ' . $servicio;
            }
        }
        $block['items'] = $items;

        return $block;
    }

    /**
     * @param array<string, int|string> $context
     * @param array<string, string> $extra
     * @return list<array<string, mixed>>
     */
    private function contextHiddenFields(array $context, string $nextStep, array $extra = []): array
    {
        $merged = array_merge($context, $extra);
        $fields = [
            [
                'name' => 'step',
                'type' => 'hidden',
                'value' => $nextStep,
                'include_in_submit' => true,
            ],
        ];
        foreach ($merged as $name => $value) {
            if ($name === 'step') {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $fields[] = [
                'name' => (string) $name,
                'type' => 'hidden',
                'value' => $text,
                'include_in_submit' => true,
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, array<string, string>> $baseline
     * @param list<string> $aspectIds
     * @return array<string, string>
     */
    private function extractProposedValues(
        string $surfaceId,
        array $params,
        array $baseline,
        array $aspectIds
    ): array {
        $surface = $this->catalog->getEditSurface($surfaceId);
        $aspects = is_array($surface['aspects'] ?? null) ? $surface['aspects'] : [];

        $out = [];
        foreach ($aspectIds as $aspectId) {
            $def = $aspects[$aspectId] ?? null;
            if (!is_array($def)) {
                continue;
            }
            $group = trim((string) ($def['attribute_group'] ?? ''));
            if ($group === '') {
                continue;
            }
            $allowed = $this->mutationAuth->allowedFieldsForAspect($def, $group);
            $aspectBaseline = $baseline[$aspectId] ?? [];
            if (!is_array($aspectBaseline)) {
                $aspectBaseline = [];
            }
            foreach ($allowed as $field) {
                if (array_key_exists($field, $params)) {
                    $out[$field] = trim((string) $params[$field]);
                } elseif (array_key_exists($field, $aspectBaseline)) {
                    $out[$field] = trim((string) $aspectBaseline[$field]);
                }
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $newBlocks
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private function appendBlocks(array $out, array $newBlocks): array
    {
        $blocks = isset($out['blocks']) && is_array($out['blocks']) ? $out['blocks'] : [];
        foreach ($newBlocks as $block) {
            $blocks[] = $block;
        }
        $out['blocks'] = $blocks;

        return $out;
    }
}
