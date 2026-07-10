<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\components\Platform\Ui\UiScreenService;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalEfectorServicio;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * UI JSON: alta/edición de cobertura EMER/IMP (roster).
 */
final class ProfesionalCoberturaUiFlowService
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function renderForm(int $idEfector, array $query, bool $allowOwnPesFallback): array
    {
        $params = self::defaults($idEfector, $query, $allowOwnPesFallback);
        $out = UiScreenService::renderUiDefinition('profesional-cobertura', 'gestionar', $params, null);
        $out['action_id'] = 'profesional-cobertura.gestionar';

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function handlePost(int $idEfector, array $post, bool $allowOwnPesFallback): array
    {
        try {
            $payload = self::preparePayload($idEfector, $post, $allowOwnPesFallback);
            $id = (int) ($post['id'] ?? 0);
            if ($id > 0) {
                $model = ProfesionalCobertura::findOne(['id' => $id, 'id_efector' => $idEfector, 'deleted_at' => null]);
                if ($model === null) {
                    throw new BadRequestHttpException('Cobertura no encontrada.');
                }
                if (!$allowOwnPesFallback) {
                    // staff: ok
                } elseif ((int) $model->id_persona !== self::requirePersonaFromSession()) {
                    throw new ForbiddenHttpException('No puede editar coberturas de otro profesional.');
                }
                $result = ProfesionalCoberturaService::actualizar($model, $payload);
            } else {
                $result = ProfesionalCoberturaService::crear($payload);
            }

            if (!$result['ok']) {
                $params = array_merge(self::defaults($idEfector, $post, $allowOwnPesFallback), $post);
                $ui = UiScreenService::renderUiDefinition('profesional-cobertura', 'gestionar', $params, $params);
                $ui['success'] = false;
                $ui['errors'] = $result['errors'] ?? ['_error' => ['No se pudo guardar.']];
                $ui['conflicts'] = $result['conflicts'] ?? [];
                $ui['action_id'] = 'profesional-cobertura.gestionar';

                return $ui;
            }

            return [
                'success' => true,
                'kind' => 'ui_submit_result',
                'action_id' => 'profesional-cobertura.gestionar',
                'data' => ProfesionalCoberturaService::toApiArray($result['model']),
                'errors' => null,
            ];
        } catch (\Throwable $e) {
            $params = array_merge(self::defaults($idEfector, $post, $allowOwnPesFallback), $post);
            $ui = UiScreenService::renderUiDefinition('profesional-cobertura', 'gestionar', $params, $params);
            $ui['success'] = false;
            $ui['errors'] = ['_error' => [$e->getMessage()]];
            $ui['action_id'] = 'profesional-cobertura.gestionar';

            return $ui;
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function defaults(int $idEfector, array $query, bool $allowOwnPesFallback): array
    {
        $classes = [];
        foreach (AgendaByEncounterClassMetadata::coberturaClasses() as $code) {
            $kind = AgendaByEncounterClassMetadata::loadConfig()['kinds'][$code] ?? [];
            $classes[] = [
                'value' => $code,
                'label' => is_array($kind) ? (string) ($kind['label'] ?? $code) : $code,
            ];
        }

        $idPes = (int) ($query['id_profesional_efector_servicio'] ?? 0);
        if ($idPes <= 0 && $allowOwnPesFallback) {
            $idPes = (int) (\Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        }

        $defaults = [
            'id_efector' => $idEfector,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : ($query['id_profesional_efector_servicio'] ?? ''),
            'encounter_class_options' => $classes,
            'encounter_class' => (string) ($query['encounter_class'] ?? 'EMER'),
        ];

        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::find()
                ->where(['id' => $idPes, 'deleted_at' => null])
                ->with('servicio')
                ->one();
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                $defaults['id_servicio'] = (int) $pes->id_servicio;
                $defaults['servicio_nombre'] = $pes->servicio !== null
                    ? (string) $pes->servicio->nombre
                    : ('Servicio #' . $pes->id_servicio);
            }
        }
        $inicio = trim((string) ($query['inicio'] ?? ''));
        $fin = trim((string) ($query['fin'] ?? ''));
        if ($inicio !== '' && empty($query['fecha_inicio'])) {
            $parts = self::splitDateTime($inicio);
            $defaults['fecha_inicio'] = $parts['fecha'];
            $defaults['hora_inicio'] = $parts['hora'];
        }
        if ($fin !== '' && empty($query['fecha_fin'])) {
            $parts = self::splitDateTime($fin);
            $defaults['fecha_fin'] = $parts['fecha'];
            $defaults['hora_fin'] = $parts['hora'];
        }
        if (empty($query['fecha_inicio']) && empty($defaults['fecha_inicio'])) {
            $defaults['fecha_inicio'] = date('Y-m-d');
            $defaults['fecha_fin'] = date('Y-m-d');
        }

        return array_merge($query, $defaults);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function preparePayload(int $idEfector, array $post, bool $allowOwnPesFallback): array
    {
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        $idPersona = (int) ($post['id_persona'] ?? 0);

        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new BadRequestHttpException('PES inválido para el efector.');
            }
            if ($allowOwnPesFallback && (int) $pes->id_persona !== self::requirePersonaFromSession()) {
                throw new ForbiddenHttpException('Solo puede cargar cobertura propia.');
            }
            $idPersona = (int) $pes->id_persona;
        } elseif ($allowOwnPesFallback) {
            $idPersona = self::requirePersonaFromSession();
        }

        if ($idPersona <= 0) {
            throw new BadRequestHttpException('id_persona o id_profesional_efector_servicio es requerido.');
        }

        $inicio = self::resolveIntervalBound($post, 'inicio', 'fecha_inicio', 'hora_inicio');
        $fin = self::resolveIntervalBound($post, 'fin', 'fecha_fin', 'hora_fin');
        if ($inicio === '' || $fin === '') {
            throw new BadRequestHttpException('Entrada y salida son obligatorias (fecha + hora).');
        }

        $idServicio = $post['id_servicio'] ?? null;
        if ($idServicio === '' || $idServicio === null) {
            $idServicio = null;
        } else {
            $idServicio = (int) $idServicio;
        }

        return [
            'id_persona' => $idPersona,
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : null,
            'encounter_class' => (string) ($post['encounter_class'] ?? ''),
            'inicio' => $inicio,
            'fin' => $fin,
            'rol' => isset($post['rol']) ? trim((string) $post['rol']) : null,
            'notas' => isset($post['notas']) ? trim((string) $post['notas']) : null,
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function resolveIntervalBound(array $post, string $fullKey, string $fechaKey, string $horaKey): string
    {
        $full = self::normalizeDateTime((string) ($post[$fullKey] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $fecha = trim((string) ($post[$fechaKey] ?? ''));
        $hora = trim((string) ($post[$horaKey] ?? ''));
        if ($fecha === '' || $hora === '') {
            return '';
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        return self::normalizeDateTime($fecha . ' ' . $hora);
    }

    /**
     * @return array{fecha: string, hora: string}
     */
    private static function splitDateTime(string $raw): array
    {
        $norm = self::normalizeDateTime($raw);
        if ($norm === '') {
            return ['fecha' => '', 'hora' => ''];
        }
        $ts = strtotime($norm);
        if ($ts === false) {
            return ['fecha' => '', 'hora' => ''];
        }

        return [
            'fecha' => date('Y-m-d', $ts),
            'hora' => date('H:i', $ts),
        ];
    }

    private static function normalizeDateTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $raw)) {
            return str_replace('T', ' ', $raw) . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $raw)) {
            return str_replace('T', ' ', $raw);
        }
        $ts = strtotime($raw);

        return $ts === false ? '' : date('Y-m-d H:i:s', $ts);
    }

    private static function requirePersonaFromSession(): int
    {
        $id = (int) (\Yii::$app->user->getIdPersona() ?? 0);
        if ($id <= 0) {
            throw new BadRequestHttpException('No hay persona en sesión.');
        }

        return $id;
    }
}
