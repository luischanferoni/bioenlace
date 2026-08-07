<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Platform\Ui\UiScreenService;
use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * UI JSON: plantilla semanal de cobertura EMER/IMP (materializa intervalos).
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
            $payload = self::preparePlantillaPayload($idEfector, $post, $allowOwnPesFallback);
            $result = ProfesionalCoberturaPlantillaService::guardarYMaterializar($payload);

            if (!$result['ok']) {
                $params = array_merge(self::defaults($idEfector, $post, $allowOwnPesFallback), $post);
                $ui = UiScreenService::renderUiDefinition('profesional-cobertura', 'gestionar', $params, $params);
                $ui['success'] = false;
                $ui['errors'] = $result['errors'] ?? ['_error' => ['No se pudo guardar.']];
                $ui['conflicts'] = $result['conflicts'] ?? [];
                $ui['action_id'] = 'profesional-cobertura.gestionar';

                return $ui;
            }

            $created = (int) ($result['created'] ?? 0);

            return [
                'success' => true,
                'kind' => 'ui_submit_result',
                'action_id' => 'profesional-cobertura.gestionar',
                'data' => [
                    'mensaje' => $created === 1
                        ? 'Se guardó el patrón y se generó 1 turno de cobertura.'
                        : ('Se guardó el patrón y se generaron ' . $created . ' turnos de cobertura.'),
                    'cobertura_ui_completed' => '1',
                    'horario_ui_completed' => '1',
                    'created' => $created,
                    'plantilla_id' => isset($result['plantilla']) ? (int) $result['plantilla']->id : null,
                ],
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
        $idPes = (int) ($query['id_profesional_efector_servicio'] ?? 0);
        if ($idPes <= 0 && $allowOwnPesFallback) {
            $idPes = (int) (\Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        }

        $encounterClass = strtoupper(trim((string) ($query['encounter_class'] ?? Encounter::ENCOUNTER_CLASS_EMER)));
        if ($encounterClass !== Encounter::ENCOUNTER_CLASS_EMER
            && $encounterClass !== Encounter::ENCOUNTER_CLASS_IMP) {
            $encounterClass = Encounter::ENCOUNTER_CLASS_EMER;
        }

        $defaults = [
            'id_efector' => $idEfector,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : ($query['id_profesional_efector_servicio'] ?? ''),
            'encounter_class' => $encounterClass,
            'vigente_desde' => date('Y-m-d'),
            'semanas' => 4,
        ];

        $idPersona = 0;
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::find()
                ->where(['id' => $idPes, 'deleted_at' => null])
                ->one();
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                $defaults['id_servicio'] = (int) $pes->id_servicio;
                $idPersona = (int) $pes->id_persona;
            }
        }
        if ($idPersona <= 0 && $allowOwnPesFallback) {
            $idPersona = (int) (\Yii::$app->user->getIdPersona() ?? 0);
        }

        if ($idPersona > 0) {
            $plantilla = ProfesionalCoberturaPlantillaService::findActivaForContext(
                $idPersona,
                $idEfector,
                $encounterClass,
                $idPes > 0 ? $idPes : null
            );
            if ($plantilla !== null) {
                $defaults['vigente_desde'] = (string) $plantilla->vigente_desde;
                $defaults['semanas'] = (int) $plantilla->semanas;
                foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
                    if (!empty($plantilla->$col)) {
                        $defaults[$col] = (string) $plantilla->$col;
                    }
                }
            }
        }

        return array_merge($query, $defaults);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function preparePlantillaPayload(int $idEfector, array $post, bool $allowOwnPesFallback): array
    {
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        $idPersona = (int) ($post['id_persona'] ?? 0);
        $pes = null;

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

        $idServicio = $post['id_servicio'] ?? null;
        if ($idServicio === '' || $idServicio === null) {
            $idServicio = $pes !== null ? (int) $pes->id_servicio : null;
        } else {
            $idServicio = (int) $idServicio;
        }

        $payload = [
            'id_persona' => $idPersona,
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : null,
            'encounter_class' => (string) ($post['encounter_class'] ?? ''),
            'vigente_desde' => (string) ($post['vigente_desde'] ?? ''),
            'semanas' => (int) ($post['semanas'] ?? 4),
        ];
        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
            $payload[$col] = $post[$col] ?? '';
        }

        return $payload;
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
