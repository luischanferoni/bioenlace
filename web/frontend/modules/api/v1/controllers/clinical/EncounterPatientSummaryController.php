<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\PatientSummary\PatientEncounterSummaryQueryService;
use Yii;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * Resumen de atención ambulatoria para el paciente autenticado.
 *
 * GET /api/v1/clinical/encounter/listar-atenciones-como-paciente
 * GET /api/v1/clinical/encounter/ver-resumen-como-paciente?encounter_id=
 * GET /api/v1/clinical/encounter/ultima-atencion-como-paciente
 */
class EncounterPatientSummaryController extends BaseController
{
    use ClinicalAccessTrait;

    private PatientEncounterSummaryQueryService $query;

    public function init()
    {
        parent::init();
        $this->query = new PatientEncounterSummaryQueryService();
    }

    public function actionListarAtencionesComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden listar atenciones.',
                null,
                400
            );
        }

        $limit = (int) Yii::$app->request->get('limit', 20);
        $offset = (int) Yii::$app->request->get('offset', 0);
        $result = $this->query->listForPersona($idPersona, $limit, $offset);

        return [
            'success' => true,
            'message' => 'Atenciones publicadas',
            'data' => $result,
        ];
    }

    public function actionVerResumenComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden ver el resumen.',
                null,
                400
            );
        }

        $encounterId = (int) Yii::$app->request->get('encounter_id', 0);
        if ($encounterId <= 0) {
            return $this->clinicalError('Se requiere encounter_id.', null, 400);
        }

        $detail = $this->query->getDetailForPersona($idPersona, $encounterId);
        if ($detail === null) {
            return $this->clinicalError('Resumen no encontrado o aún no publicado.', null, 404);
        }

        return [
            'success' => true,
            'message' => 'Resumen de atención',
            'data' => $detail,
        ];
    }

    public function actionUltimaAtencionComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden ver la última atención.',
                null,
                400
            );
        }

        $detail = $this->query->getLatestForPersona($idPersona);
        if ($detail === null) {
            return $this->clinicalError('No hay atenciones publicadas.', null, 404);
        }

        return [
            'success' => true,
            'message' => 'Última atención',
            'data' => $detail,
        ];
    }
}
