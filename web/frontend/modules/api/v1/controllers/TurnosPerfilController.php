<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoAgentActionExplanationService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorAggregateService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileViewService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Perfil factual de turnos.
 *
 * - GET historial-propio-como-paciente
 * - GET historial-representado-como-paciente?subject_persona_id=
 * - GET explicacion-accion-propia-como-paciente?id_turno=
 * - GET agregado-efector-para-staff?id_efector=&window_days=
 */
final class TurnosPerfilController extends BaseController
{
    public function actionHistorialPropioComoPaciente(): array
    {
        $data = (new TurnoBehaviorProfileViewService())->forPerson(
            (int) Yii::$app->user->getIdPersona()
        );

        return $this->success($data, 'Historial de turnos');
    }

    public function actionHistorialRepresentadoComoPaciente(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());
        $subjectPersonaId = (int) ($params['subject_persona_id'] ?? $params['id_persona_sujeto'] ?? 0);
        if ($subjectPersonaId <= 0) {
            throw new BadRequestHttpException('subject_persona_id es obligatorio');
        }
        $actorPersonaId = (int) Yii::$app->user->getIdPersona();
        if ($subjectPersonaId === $actorPersonaId) {
            throw new BadRequestHttpException('Usá el endpoint de historial propio para tu propio perfil');
        }

        $subjectSvc = new PersonRepresentationSubjectService();
        $subjectSvc->assertCanAct($subjectPersonaId, RepresentationPermission::SCHEDULING_TURNO);

        $data = (new TurnoBehaviorProfileViewService())->forPerson($subjectPersonaId);
        $data['subject_context'] = 'represented';
        $data['subject_persona_id'] = $subjectPersonaId;

        return $this->success($data, 'Historial de turnos representado');
    }

    public function actionExplicacionAccionPropiaComoPaciente(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());
        $idTurno = (int) ($params['id_turno'] ?? $params['id'] ?? 0);
        $decisionRef = isset($params['decision_ref']) ? (int) $params['decision_ref'] : null;
        if ($idTurno <= 0) {
            throw new BadRequestHttpException('id_turno es obligatorio');
        }

        try {
            $data = (new TurnoAgentActionExplanationService())->explainOwnAction(
                (int) Yii::$app->user->getIdPersona(),
                $idTurno,
                $decisionRef
            );
        } catch (\InvalidArgumentException $e) {
            throw new ForbiddenHttpException($e->getMessage());
        }

        return $this->success($data, 'Explicación de acción');
    }

    public function actionAgregadoEfectorParaStaff(): array
    {
        $params = array_merge(Yii::$app->request->get(), Yii::$app->request->post());
        $idEfector = EfectorAccessService::assertAndResolveIdEfector(
            'turnos.indicadores-agenda-flow',
            $params
        );
        $windowDays = (int) ($params['window_days'] ?? 90);

        try {
            $data = (new TurnoBehaviorAggregateService())->forEfector([
                'id_efector' => $idEfector,
                'window_days' => $windowDays,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->success($data, 'Agregado factual de turnos');
    }
}
