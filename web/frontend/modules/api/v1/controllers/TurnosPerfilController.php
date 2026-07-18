<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoAgentActionExplanationService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorAggregateService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorCorrectionService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileViewService;
use common\components\Platform\Ui\UiScreenService;
use common\models\Platform\AgentRun;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Perfil factual de turnos (historial, explicación, agregados y correcciones).
 */
final class TurnosPerfilController extends BaseController
{
    public function actionHistorialPropioComoPaciente(): array
    {
        $data = (new TurnoBehaviorProfileViewService())->forPerson(
            (int) Yii::$app->user->getIdPersona()
        );
        $values = $this->historialValues($data);
        $ui = UiScreenService::renderUiDefinition('turnos-perfil', 'historial-propio-como-paciente', Yii::$app->request->get(), $values);
        $ui = UiScreenService::withListBlockItems($ui, $this->metricItems($data), 'metricas');
        $ui['data'] = $data;

        return $ui;
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

        (new PersonRepresentationSubjectService())
            ->assertCanAct($subjectPersonaId, RepresentationPermission::SCHEDULING_TURNO);

        $data = (new TurnoBehaviorProfileViewService())->forPerson($subjectPersonaId);
        $data['subject_context'] = 'represented';
        $data['subject_persona_id'] = $subjectPersonaId;
        $values = $this->historialValues($data);
        $ui = UiScreenService::renderUiDefinition(
            'turnos-perfil',
            'historial-representado-como-paciente',
            $params,
            $values
        );
        $ui = UiScreenService::withListBlockItems($ui, $this->metricItems($data), 'metricas');
        $ui['data'] = $data;

        return $ui;
    }

    public function actionExplicacionAccionPropiaComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'turnos-perfil',
            'explicacion-accion-propia-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $idTurno = (int) ($post['id_turno'] ?? $post['id'] ?? 0);
                $decisionRef = isset($post['decision_ref']) ? (int) $post['decision_ref'] : null;
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

                return [
                    'data' => $data,
                    'values' => [
                        'id_turno' => (string) $idTurno,
                        'explanation_text' => (string) ($data['explanation_text'] ?? $data['message'] ?? ''),
                        'disclaimer' => (string) ($data['disclaimer'] ?? ''),
                    ],
                ];
            }
        );
    }

    public function actionAgregadoEfectorParaStaff(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        if ($req->isPost) {
            try {
                $idEfector = EfectorAccessService::assertAndResolveIdEfector(
                    'turnos.indicadores-agenda-flow',
                    $params
                );
                $windowDays = (int) ($params['window_days'] ?? 90);
                $data = (new TurnoBehaviorAggregateService())->forEfector([
                    'id_efector' => $idEfector,
                    'window_days' => $windowDays,
                ]);
                $values = [
                    'id_efector' => (string) $idEfector,
                    'window_days' => (string) $windowDays,
                    'status_text' => $this->aggregateStatusText($data),
                ];
                $ui = UiScreenService::renderUiDefinition(
                    'turnos-perfil',
                    'agregado-efector-para-staff',
                    $req->get(),
                    $values
                );
                $ui = UiScreenService::withListBlockItems($ui, $this->aggregateMetricItems($data), 'metricas');
                $ui['success'] = true;
                $ui['data'] = $data;

                return $ui;
            } catch (\Throwable $e) {
                $ui = UiScreenService::renderUiDefinition(
                    'turnos-perfil',
                    'agregado-efector-para-staff',
                    $req->get(),
                    $params
                );
                $ui['success'] = false;
                $ui['errors'] = ['_error' => [$e->getMessage()]];

                return $ui;
            }
        }

        return UiScreenService::renderUiDefinition(
            'turnos-perfil',
            'agregado-efector-para-staff',
            $params,
            null
        );
    }

    public function actionSolicitarCorreccionPropiaComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'turnos-perfil',
            'solicitar-correccion-propia-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $idTurno = (int) ($post['id_turno'] ?? 0);
                $claim = (string) ($post['claim_code'] ?? '');
                $correctedEventId = isset($post['corrected_event_id'])
                    ? (int) $post['corrected_event_id']
                    : null;
                $data = (new TurnoBehaviorCorrectionService())->request(
                    (int) Yii::$app->user->getIdPersona(),
                    $idTurno,
                    $claim,
                    $correctedEventId,
                    TurnoEventoAudit::ACTOR_PACIENTE
                );

                return ['data' => $data, 'values' => ['message' => (string) ($data['message'] ?? '')]];
            }
        );
    }

    public function actionSolicitarCorreccionRepresentadaComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'turnos-perfil',
            'solicitar-correccion-representada-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $subjectPersonaId = (int) ($post['subject_persona_id'] ?? 0);
                if ($subjectPersonaId <= 0) {
                    throw new BadRequestHttpException('subject_persona_id es obligatorio');
                }
                $actorPersonaId = (int) Yii::$app->user->getIdPersona();
                if ($subjectPersonaId === $actorPersonaId) {
                    throw new BadRequestHttpException('Usá el endpoint propio para tu propio perfil');
                }
                (new PersonRepresentationSubjectService())
                    ->assertCanAct($subjectPersonaId, RepresentationPermission::SCHEDULING_TURNO);

                $data = (new TurnoBehaviorCorrectionService())->request(
                    $subjectPersonaId,
                    (int) ($post['id_turno'] ?? 0),
                    (string) ($post['claim_code'] ?? ''),
                    isset($post['corrected_event_id']) ? (int) $post['corrected_event_id'] : null,
                    TurnoEventoAudit::ACTOR_REPRESENTANTE
                );

                return ['data' => $data, 'values' => ['message' => (string) ($data['message'] ?? '')]];
            }
        );
    }

    public function actionListarCorreccionesEfectorParaStaff(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $idEfector = EfectorAccessService::assertAndResolveIdEfector(
            'turnos.indicadores-agenda-flow',
            $params
        );
        $data = (new TurnoBehaviorCorrectionService())->listPendingForEfector($idEfector);
        $ui = UiScreenService::renderUiDefinition(
            'turnos-perfil',
            'listar-correcciones-efector-para-staff',
            $params,
            ['id_efector' => (string) $idEfector]
        );
        $ui = UiScreenService::withListBlockItems($ui, $data['items'] ?? [], 'correcciones');
        $ui['data'] = $data;

        return $ui;
    }

    public function actionResolverCorreccionParaStaff(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'turnos-perfil',
            'resolver-correccion-para-staff',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $correctionRef = (int) ($post['correction_ref'] ?? 0);
                $decision = (string) ($post['decision'] ?? '');
                $replacement = isset($post['replacement_outcome'])
                    ? trim((string) $post['replacement_outcome'])
                    : null;
                if ($replacement === '') {
                    $replacement = null;
                }

                /** @var AgentRun|null $request */
                $request = AgentRun::findOne([
                    'id' => $correctionRef,
                    'agent_id' => TurnoBehaviorCorrectionService::AGENT_ID,
                ]);
                if ($request === null) {
                    throw new BadRequestHttpException('Solicitud no encontrada');
                }
                $turno = Turno::findOne(['id_turnos' => (int) $request->trigger_id]);
                if ($turno === null) {
                    throw new BadRequestHttpException('Turno asociado no encontrado');
                }
                EfectorAccessService::assertAndResolveIdEfector(
                    'turnos.indicadores-agenda-flow',
                    ['id_efector' => (int) $turno->id_efector]
                );

                $data = (new TurnoBehaviorCorrectionService())->resolve(
                    $correctionRef,
                    $decision,
                    $replacement,
                    Yii::$app->user->id ?? null
                );

                return [
                    'data' => $data,
                    'values' => [
                        'status_text' => 'Resolución: ' . strtoupper((string) ($data['status'] ?? '')),
                        'correction_ref' => (string) $correctionRef,
                        'decision' => $decision,
                    ],
                ];
            }
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function historialValues(array $data): array
    {
        $status = (string) ($data['status'] ?? 'UNAVAILABLE');
        $statusText = $status === 'CURRENT'
            ? 'Historial actualizado al ' . (string) ($data['as_of'] ?? '')
            : (string) ($data['message'] ?? 'Todavía no hay información suficiente.');

        return [
            'disclaimer' => (string) ($data['disclaimer'] ?? ''),
            'status_text' => $statusText,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function metricItems(array $data): array
    {
        $items = [];
        foreach ($data['metrics'] ?? [] as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            if ((string) ($metric['scope_type'] ?? '') !== 'GLOBAL') {
                continue;
            }
            if ((int) ($metric['window_days'] ?? 0) !== 90) {
                continue;
            }
            $code = (string) ($metric['code'] ?? '');
            $value = $metric['value'];
            $label = $code;
            if ($value !== null) {
                $label .= ': ' . $value;
            } else {
                $label .= ' (' . (string) ($metric['confidence_status'] ?? '') . ')';
            }
            $items[] = [
                'id' => $code . '-90',
                'name' => $label,
                'code' => $code,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function aggregateStatusText(array $data): string
    {
        if (($data['status'] ?? '') === 'SUPPRESSED_SMALL_COHORT') {
            return (string) ($data['suppression_reason'] ?? 'Cohorte insuficiente.');
        }

        return 'Agregado factual disponible.';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function aggregateMetricItems(array $data): array
    {
        $items = [];
        foreach ($data['metrics'] ?? [] as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $code = (string) ($metric['code'] ?? '');
            $value = $metric['value'];
            $items[] = [
                'id' => $code,
                'name' => $code . ($value !== null ? ': ' . $value : ''),
            ];
        }

        return $items;
    }
}
