<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterJourneyService;
use common\components\Domain\Clinical\Service\EncounterJourney\EncounterMotivosIntakeService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;

/**
 * Recorrido pre/post consulta del paciente (ventanas, elegibilidad, acciones).
 *
 * RBAC ApiGhost: /api/encounter-journey/&lt;action&gt;
 */
class EncounterJourneyController extends BaseController
{
    /**
     * GET|POST /api/v1/encounter-journey/estado
     *
     * Query/body: turno_id (obligatorio) o encounter_id; subject_persona_id si representación.
     *
     * @action_name Estado journey pre/post consulta
     * @entity EncounterJourney
     * @tags paciente, turnos, motivos, pre-consulta
     */
    public function actionEstado(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());

        $turno = $this->resolveTurno($params);
        $encounter = $this->resolveEncounter($params, $turno);

        $idPersona = (new PersonRepresentationSubjectService())->resolveAndAuthorize(
            $params,
            RepresentationPermission::SCHEDULING_TURNO
        );
        if ((int) $turno->id_persona !== $idPersona) {
            throw new ForbiddenHttpException('No tenés permiso para ver este turno.');
        }

        $journeySvc = new EncounterJourneyService();
        $journey = $journeySvc->buildForTurno($turno, $encounter);
        $legacy = $journeySvc->legacyFlagsForTurno($turno, $encounter);

        return [
            'success' => true,
            'turno_id' => (int) $turno->id_turnos,
            'encounter_id' => $encounter !== null ? (int) $encounter->id : null,
            'journey' => $journey,
            'motivos_input_abierto' => $legacy['motivos_input_abierto'],
            'motivos_cierre_minutos' => $legacy['motivos_cierre_minutos'],
            'asistencia_cohorte_disponible' => $legacy['asistencia_cohorte_disponible'],
        ];
    }

    /**
     * GET|POST /api/v1/encounter-journey/motivos-intake
     *
     * Query/body: turno_id o encounter_id; subject_persona_id si representación.
     *
     * @action_name Preguntas previas motivos consulta
     * @entity EncounterJourney
     * @tags paciente, turnos, motivos
     */
    public function actionMotivosIntake(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $service = new EncounterMotivosIntakeService();

        try {
            if ($req->isPost) {
                return $service->submitIntake($params);
            }

            return $service->renderIntake($params);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveTurno(array $params): Turno
    {
        $turnoId = isset($params['turno_id']) ? (int) $params['turno_id'] : 0;
        $encounterId = isset($params['encounter_id']) ? (int) $params['encounter_id'] : 0;

        if ($turnoId > 0) {
            $turno = Turno::findActive()->andWhere(['id_turnos' => $turnoId])->one();
            if ($turno instanceof Turno) {
                return $turno;
            }
            throw new NotFoundHttpException('Turno no encontrado.');
        }

        if ($encounterId > 0) {
            $encounter = Encounter::findOne($encounterId);
            if ($encounter === null || !(int) $encounter->appointment_id) {
                throw new NotFoundHttpException('Encounter sin turno vinculado.');
            }
            $turno = Turno::findActive()->andWhere(['id_turnos' => (int) $encounter->appointment_id])->one();
            if ($turno instanceof Turno) {
                return $turno;
            }
            throw new NotFoundHttpException('Turno no encontrado.');
        }

        throw new BadRequestHttpException('turno_id o encounter_id es obligatorio.');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveEncounter(array $params, Turno $turno): ?Encounter
    {
        $encounterId = isset($params['encounter_id']) ? (int) $params['encounter_id'] : 0;
        if ($encounterId > 0) {
            return Encounter::findOne($encounterId);
        }

        return Encounter::findOne(['appointment_id' => (int) $turno->id_turnos]);
    }
}
