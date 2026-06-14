<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Platform\Ui\UiScreenService;
use common\models\ProfesionalEfectorServicioAgenda;
use Yii;

/**
 * Flujo UI configurar agenda: datos → (impacto) → persistir.
 */
final class AgendaConfigUiFlowService
{
    public const STEP_DATOS = 'datos';
    public const STEP_IMPACTO = 'impacto';

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function renderStep(int $idEfector, array $query): array
    {
        $step = self::normalizeStep((string) ($query['ui_step'] ?? self::STEP_DATOS));
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildFieldValuesForGet($idEfector, $query);
        $params = array_merge($defaults, $query);
        $params['ui_step'] = $step;
        $params['today'] = date('Y-m-d');

        if ($step === self::STEP_IMPACTO) {
            return self::renderImpactoStep($params);
        }

        return self::renderDatosStep($params);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function handlePost(int $idEfector, array $post): array
    {
        $step = self::normalizeStep((string) ($post['ui_step'] ?? self::STEP_DATOS));
        $post = self::mergeWithAgendaDefaults($idEfector, $post);

        if ($step === self::STEP_IMPACTO) {
            $post['confirmar_cambios'] = '1';

            try {
                $data = ProfesionalEfectorServicioAgendaUiService::submitAgendaConfig($idEfector, $post);

                return [
                    'success' => true,
                    'kind' => 'ui_submit_result',
                    'action_id' => 'profesional-agenda.configurar-agenda',
                    'data' => $data,
                    'errors' => null,
                ];
            } catch (\Throwable $e) {
                $impactParams = array_merge($post, [
                    'ui_step' => self::STEP_IMPACTO,
                    'preview_message' => $e->getMessage(),
                ]);
                $ui = self::renderImpactoStep($impactParams);
                $ui['success'] = false;
                $ui['errors'] = ['_error' => [$e->getMessage()]];
                $ui['values'] = $post;

                return $ui;
            }
        }

        $idPes = ProfesionalEfectorServicioAgendaUiService::resolvePesIdForAgendaSubmitPublic($idEfector, $post);
        $preview = ProfesionalEfectorServicioAgendaVersionService::previewImpacto($idPes, $idEfector, $post);

        if (self::mustRouteThroughImpactStep($idPes, $post)
            || AgendaConfigImpactProfile::previewRequiresUserConfirmation($preview, $post)) {
            $impactParams = array_merge($post, [
                'ui_step' => self::STEP_IMPACTO,
                'preview_message' => (string) ($preview['mensaje'] ?? ''),
                'requiere_confirmacion' => AgendaConfigImpactProfile::previewRequiresUserConfirmation($preview, $post)
                    ? '1'
                    : '0',
            ]);

            return self::renderImpactoStep($impactParams);
        }

        if (AgendaConfigImpactProfile::isModalityOnlySubmit($post)) {
            $post['forzar_sin_confirmacion'] = '1';
        }

        return [
            'success' => true,
            'kind' => 'ui_submit_result',
            'action_id' => 'profesional-agenda.configurar-agenda',
            'data' => ProfesionalEfectorServicioAgendaUiService::submitAgendaConfig($idEfector, $post),
            'errors' => null,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function renderDatosStep(array $params): array
    {
        $onlyFields = self::parseFieldsFilter($params);
        $out = UiScreenService::renderUiDefinition('profesional-agenda', 'configurar-agenda', $params, null);
        $out = self::filterUiBlocks($out, self::STEP_DATOS, $onlyFields);
        $out['action_id'] = 'profesional-agenda.configurar-agenda';
        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'ui_step' => self::STEP_DATOS,
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function renderImpactoStep(array $params): array
    {
        $out = UiScreenService::renderUiDefinition('profesional-agenda', 'preview-impacto-agenda', $params, null);
        $out['action_id'] = 'profesional-agenda.preview-impacto-agenda';
        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = [
            'ui_step' => self::STEP_IMPACTO,
            'requiere_confirmacion' => ($params['requiere_confirmacion'] ?? '') === '1',
        ];

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function mergeWithAgendaDefaults(int $idEfector, array $post): array
    {
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildFieldValuesForGet($idEfector, $post);
        $merged = array_merge($defaults, $post);
        $onlyFields = self::parseFieldsFilter($post);
        if ($onlyFields !== null) {
            foreach ($defaults as $key => $value) {
                if (!in_array($key, $onlyFields, true) && !in_array($key, ['id_efector', 'id_profesional_efector_servicio', 'id_servicio'], true)) {
                    if (!array_key_exists($key, $post)) {
                        $merged[$key] = $value;
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>|null
     */
    private static function parseFieldsFilter(array $params): ?array
    {
        $raw = trim((string) ($params['fields'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $s): bool => $s !== ''));

        return $parts === [] ? null : $parts;
    }

    private static function normalizeStep(string $step): string
    {
        $step = mb_strtolower(trim($step), 'UTF-8');

        return $step === self::STEP_IMPACTO ? self::STEP_IMPACTO : self::STEP_DATOS;
    }

    /**
     * Edición de agenda existente con cambios de grilla: obliga paso de impacto (no aplica al alta inicial).
     *
     * @param array<string, mixed> $post
     */
    private static function mustRouteThroughImpactStep(int $idPes, array $post): bool
    {
        $policy = trim((string) ($post['impact_preview_policy'] ?? ''));
        if ($policy !== 'when_existing_agenda') {
            return false;
        }
        if ($idPes <= 0) {
            return false;
        }
        if (ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes) === null) {
            return false;
        }

        return AgendaConfigImpactProfile::postTouchesGridFields($post);
    }

    /**
     * @param list<string>|null $onlyFields
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private static function filterUiBlocks(array $out, string $step, ?array $onlyFields): array
    {
        if (!isset($out['blocks']) || !is_array($out['blocks'])) {
            return $out;
        }

        $meta = isset($out['ui_meta']['field_meta']) && is_array($out['ui_meta']['field_meta'])
            ? $out['ui_meta']['field_meta']
            : [];

        foreach ($out['blocks'] as $idx => $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                continue;
            }
            $blockId = trim((string) ($block['id'] ?? ''));
            if ($step === self::STEP_DATOS && $blockId !== '' && $blockId !== 'datos') {
                unset($out['blocks'][$idx]);
                continue;
            }
            if (!isset($block['fields']) || !is_array($block['fields'])) {
                continue;
            }
            $fields = [];
            foreach ($block['fields'] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = trim((string) ($field['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                if ($onlyFields !== null && !in_array($name, $onlyFields, true)
                    && !in_array($name, ['id_efector', 'id_profesional_efector_servicio', 'id_servicio'], true)) {
                    continue;
                }
                if ($onlyFields === null && isset($meta[$name]['impact_profile']) && $meta[$name]['impact_profile'] === 'none') {
                    // En formulario completo se muestran todos los datos editables.
                }
                $fields[] = $field;
            }
            $block['fields'] = $fields;
            $block['submit_api'] = [
                'route' => '/api/v1/profesional-agenda/configurar-agenda',
                'method' => 'POST',
            ];
            $out['blocks'][$idx] = $block;
        }

        $out['blocks'] = array_values($out['blocks']);

        return $out;
    }
}
