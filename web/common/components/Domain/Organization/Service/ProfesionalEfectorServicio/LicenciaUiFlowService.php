<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Scheduling\Service\TurnoIndisponibilidadImpactService;
use common\components\Platform\Ui\UiScreenService;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Flujo UI licencia: fechas → (impacto en turnos) → persistir condición + marcar turnos.
 */
final class LicenciaUiFlowService
{
    public const STEP_DATOS = 'datos';
    public const STEP_IMPACTO = 'impacto';

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function renderGet(
        int $idEfector,
        array $query,
        string $entity,
        string $action,
        string $defaultIntentId,
        bool $allowOwnPesFallback = true
    ): array {
        $step = self::normalizeStep((string) ($query['ui_step'] ?? ''));
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildLicenciaValuesForGet(
            $idEfector,
            $query,
            $allowOwnPesFallback
        );
        $params = array_merge($defaults, $query);
        $params['ui_step'] = $step;

        if ($step === self::STEP_IMPACTO) {
            return self::renderImpactoStep($entity, $action, $params);
        }

        return self::renderDatosStep($entity, $action, $params);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function handlePost(
        int $idEfector,
        array $post,
        string $entity,
        string $action,
        string $defaultIntentId,
        bool $allowOwnPesFallback = true
    ): array {
        $actionId = strtolower($entity . '.' . $action);
        $step = self::normalizeStep((string) ($post['ui_step'] ?? ''));

        if ($step === self::STEP_IMPACTO) {
            $post['confirmar_impacto_turnos'] = '1';

            return self::finalizeLicencia($idEfector, $post, $defaultIntentId, $actionId, $allowOwnPesFallback);
        }

        try {
            $prepared = ProfesionalEfectorServicioAgendaUiService::prepareCondicionLaboralSubmit(
                $idEfector,
                $post,
                $defaultIntentId,
                $allowOwnPesFallback
            );
        } catch (BadRequestHttpException|ForbiddenHttpException $e) {
            return self::errorUiDefinition($entity, $action, $post, $e->getMessage(), $allowOwnPesFallback, $idEfector);
        }

        $fi = (string) ($prepared['fecha_inicio'] ?? '');
        if ($fi === '') {
            throw new BadRequestHttpException('fecha_inicio es obligatoria.');
        }
        $ff = $prepared['fecha_fin'] !== null ? (string) $prepared['fecha_fin'] : null;

        $preview = TurnoIndisponibilidadImpactService::previewPorPesYRango(
            (int) $prepared['id_pes'],
            $fi,
            $ff
        );

        if ((int) ($preview['turnos_afectados_total'] ?? 0) > 0) {
            $impactParams = array_merge($post, [
                'ui_step' => self::STEP_IMPACTO,
                'preview_message' => (string) ($preview['mensaje'] ?? ''),
                'requiere_confirmacion' => '1',
                'fecha_inicio' => $fi,
                'fecha_fin' => $ff ?? '',
                'intent_id' => (string) $prepared['intent_id'],
                'id_profesional_efector_servicio' => (int) $prepared['id_pes'],
            ]);

            return self::renderImpactoStep($entity, $action, $impactParams);
        }

        return self::finalizeLicencia($idEfector, $post, $defaultIntentId, $actionId, $allowOwnPesFallback);
    }

    /**
     * Cierre declarativo del asistente (`flow_submit`).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function handleFlowSubmit(
        int $idEfector,
        array $post,
        string $defaultIntentId,
        string $flowActionId,
        bool $allowOwnPesFallback
    ): array {
        $entity = 'profesional-efector-servicio';
        $action = str_contains($flowActionId, 'para-profesional')
            ? 'cargar-licencia-para-profesional'
            : 'cargar-licencia-como-profesional';

        if (!empty($post['confirmar_impacto_turnos']) && (string) $post['confirmar_impacto_turnos'] !== '0') {
            $post['ui_step'] = self::STEP_IMPACTO;
        }

        $result = self::handlePost($idEfector, $post, $entity, $action, $defaultIntentId, $allowOwnPesFallback);
        if (($result['kind'] ?? '') === 'ui_definition') {
            return $result;
        }

        $result['action_id'] = $flowActionId;

        return $result;
    }

    /**
     * Preview JSON para widget embebido (sin persistir).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function previewImpacto(int $idEfector, array $post, string $defaultIntentId, bool $allowOwnPesFallback): array
    {
        $prepared = ProfesionalEfectorServicioAgendaUiService::prepareCondicionLaboralSubmit(
            $idEfector,
            $post,
            $defaultIntentId,
            $allowOwnPesFallback
        );
        $fi = (string) ($prepared['fecha_inicio'] ?? '');
        if ($fi === '') {
            throw new BadRequestHttpException('fecha_inicio es obligatoria para calcular el impacto.');
        }
        $ff = $prepared['fecha_fin'] !== null ? (string) $prepared['fecha_fin'] : null;

        return TurnoIndisponibilidadImpactService::previewPorPesYRango((int) $prepared['id_pes'], $fi, $ff);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function finalizeLicencia(
        int $idEfector,
        array $post,
        string $defaultIntentId,
        string $actionId,
        bool $allowOwnPesFallback
    ): array {
        try {
            $prepared = ProfesionalEfectorServicioAgendaUiService::prepareCondicionLaboralSubmit(
                $idEfector,
                $post,
                $defaultIntentId,
                $allowOwnPesFallback
            );
        } catch (BadRequestHttpException|ForbiddenHttpException $e) {
            $entity = explode('.', $actionId, 2)[0] ?? 'profesional-efector-servicio';
            $action = explode('.', $actionId, 2)[1] ?? 'cargar-licencia-como-profesional';

            return self::errorUiDefinition($entity, $action, $post, $e->getMessage(), $allowOwnPesFallback, $idEfector);
        }

        $fi = (string) ($prepared['fecha_inicio'] ?? '');
        $ff = $prepared['fecha_fin'] !== null ? (string) $prepared['fecha_fin'] : null;
        $preview = TurnoIndisponibilidadImpactService::previewPorPesYRango((int) $prepared['id_pes'], $fi, $ff);
        $needsConfirm = (int) ($preview['turnos_afectados_total'] ?? 0) > 0;
        $confirmed = self::isConfirmImpactoPost($post);

        if ($needsConfirm && !$confirmed) {
            throw new BadRequestHttpException(
                'Hay turnos pendientes en el período. Revisá el impacto y confirmá antes de guardar la licencia.'
            );
        }

        $data = ProfesionalEfectorServicioAgendaUiService::persistPreparedCondicionLaboral($prepared);

        if ($needsConfirm && $confirmed) {
            $marcados = TurnoIndisponibilidadImpactService::aplicarPorLicencia(
                (int) $prepared['id_pes'],
                $fi,
                $ff
            );
            if ($marcados > 0) {
                $data['turnos_afectados'] = $marcados;
                $sufijo = $marcados === 1
                    ? ' 1 turno pasó a resolución; el paciente puede reubicar o cancelar.'
                    : ' ' . $marcados . ' turnos pasaron a resolución; los pacientes pueden reubicar o cancelar.';
                $data['mensaje'] = rtrim((string) ($data['mensaje'] ?? ''), '.') . '.' . $sufijo;
            }
        }

        return [
            'success' => true,
            'kind' => 'ui_submit_result',
            'action_id' => $actionId,
            'data' => $data,
            'errors' => null,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function renderDatosStep(string $entity, string $action, array $params): array
    {
        $out = UiScreenService::renderUiDefinition($entity, $action, $params, null);
        $out['action_id'] = strtolower($entity . '.' . $action);
        $out['kind'] = 'ui_definition';
        $out['success'] = true;
        $out['data'] = ['ui_step' => self::STEP_DATOS];

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function renderImpactoStep(string $entity, string $action, array $params): array
    {
        $out = UiScreenService::renderUiDefinition($entity, 'preview-impacto-licencia', $params, null);
        $out['action_id'] = strtolower($entity . '.' . $action);
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
     */
    private static function errorUiDefinition(
        string $entity,
        string $action,
        array $post,
        string $message,
        bool $allowOwnPesFallback,
        int $idEfector
    ): array {
        $defaults = ProfesionalEfectorServicioAgendaUiService::buildLicenciaValuesForGet(
            $idEfector,
            $post,
            $allowOwnPesFallback
        );
        $params = array_merge($defaults, $post);
        $ui = self::renderDatosStep($entity, $action, $params);
        $ui['success'] = false;
        $ui['errors'] = ['_error' => [$message]];
        $ui['values'] = $post;

        return $ui;
    }

    private static function normalizeStep(string $step): string
    {
        $step = mb_strtolower(trim($step), 'UTF-8');

        return $step === self::STEP_IMPACTO ? self::STEP_IMPACTO : self::STEP_DATOS;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function isConfirmImpactoPost(array $post): bool
    {
        if (self::normalizeStep((string) ($post['ui_step'] ?? '')) === self::STEP_IMPACTO) {
            return true;
        }

        return !empty($post['confirmar_impacto_turnos']) && (string) $post['confirmar_impacto_turnos'] !== '0';
    }
}
