<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Service\EncounterAppointmentReasonLookupService;
use common\components\Domain\Clinical\Service\StaffEncounterConsultaViewService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Resumen de consulta documentada para personal de salud (solo lectura).
 *
 * GET /api/v1/clinical/encounter/ver-consulta-como-staff?turno_id= | encounter_id=
 *
 * No usar personas/.../historia-clinica para “Ver consulta” de un turno atendido.
 */
class EncounterStaffSummaryController extends BaseController
{
    use ClinicalAccessTrait;

    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * GET — detalle de lo cargado por el médico en un encounter (vía turno_id o encounter_id).
     *
     * Ámbito: staff con acceso al encounter. No abre captura.
     */
    public function actionVerConsultaComoStaff(): array
    {
        $turnoId = (int) Yii::$app->request->get('turno_id', 0);
        $encounterId = (int) Yii::$app->request->get('encounter_id', 0);
        if ($turnoId <= 0 && $encounterId <= 0) {
            return $this->clinicalError('Indicá turno_id o encounter_id.', null, 400);
        }

        $resolvedEncounterId = $encounterId;
        if ($turnoId > 0) {
            $resolvedEncounterId = (int) ((new EncounterAppointmentReasonLookupService())
                ->encounterIdParaTurno($turnoId) ?? 0);
            if ($resolvedEncounterId <= 0) {
                return $this->clinicalError(
                    'No hay encounter documentado para este turno.',
                    null,
                    404
                );
            }
        }

        [$encounter, $err] = $this->requireEncounterAccess($resolvedEncounterId);
        if ($err !== null) {
            return $err;
        }
        unset($encounter);

        $built = (new StaffEncounterConsultaViewService())->build(
            $turnoId > 0 ? $turnoId : null,
            $resolvedEncounterId
        );
        if (empty($built['ok'])) {
            $status = (int) ($built['http_status'] ?? 400);

            return $this->clinicalError(
                (string) ($built['message'] ?? 'No se pudo cargar la consulta.'),
                null,
                $status
            );
        }

        return [
            'success' => true,
            'message' => 'OK',
            'data' => $built['data'],
        ];
    }
}
