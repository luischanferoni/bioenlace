<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use common\components\Domain\Person\Representation\Service\PatientDelegationService;
use common\components\Domain\Person\Representation\Service\VerifiedGuardianshipService;
use common\components\Platform\Ui\UiScreenService;

/**
 * Representación operativa paciente — régimen A (tutela), régimen B (delegación) y staff.
 *
 * RBAC ApiGhost: /api/person-representation/&lt;action&gt;
 */
class PersonRepresentationController extends BaseController
{
    /**
     * POST /api/v1/person-representation/solicitar-menor-como-tutor
     *
     * Body: relationship_type_code, id_persona | documento (+ sexo para RENAPER), evidence (tutor legal).
     *
     * @action_name Solicitar tutela de menor
     * @entity PersonRepresentation
     * @tags paciente, tutela, menor, representación
     */
    public function actionSolicitarMenorComoTutor(): array
    {
        $this->assertPost();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        try {
            return (new VerifiedGuardianshipService())->solicitarMenorComoTutor(
                $idPersona,
                $this->mergedParams()
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * GET|POST /api/v1/person-representation/mis-vinculos-como-tutor
     *
     * Query/body opcional: status (pending|active|blocked|revoked).
     *
     * @action_name Mis vínculos como tutor
     * @entity PersonRepresentation
     * @tags paciente, tutela, menor
     */
    public function actionMisVinculosComoTutor(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $params = $this->mergedParams();
        $status = isset($params['status']) ? trim((string) $params['status']) : null;

        return (new VerifiedGuardianshipService())->listarMisVinculosComoTutor($idPersona, $status);
    }

    /**
     * POST /api/v1/person-representation/verificar-vinculo-para-staff
     *
     * Body: person_related_id, nota (opcional).
     *
     * @action_name Verificar vínculo de tutela (staff)
     * @entity PersonRepresentation
     * @tags staff, tutela, verificación
     */
    public function actionVerificarVinculoParaStaff(): array
    {
        $this->assertPost();
        $staffUserId = (int) Yii::$app->user->id;

        try {
            return (new VerifiedGuardianshipService())->verificarVinculoParaStaff(
                $staffUserId,
                $this->mergedParams()
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * POST /api/v1/person-representation/bloquear-para-staff
     *
     * Body: person_related_id, blocked_reason, nota (opcional).
     *
     * @action_name Bloquear vínculo de representación (staff)
     * @entity PersonRepresentation
     * @tags staff, tutela, bloqueo
     */
    public function actionBloquearParaStaff(): array
    {
        $this->assertPost();
        $staffUserId = (int) Yii::$app->user->id;

        try {
            return (new VerifiedGuardianshipService())->bloquearParaStaff(
                $staffUserId,
                $this->mergedParams()
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * POST /api/v1/person-representation/revocar-para-staff
     *
     * Body: person_related_id, nota (opcional).
     *
     * @action_name Revocar vínculo de representación (staff)
     * @entity PersonRepresentation
     * @tags staff, tutela, revocación
     */
    public function actionRevocarParaStaff(): array
    {
        $this->assertPost();
        $staffUserId = (int) Yii::$app->user->id;

        try {
            return (new VerifiedGuardianshipService())->revocarParaStaff(
                $staffUserId,
                $this->mergedParams()
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * GET|POST /api/v1/person-representation/vinculos-paciente-para-staff
     *
     * Query/body: id_persona (sujeto).
     *
     * @action_name Vínculos de representación de un paciente (staff)
     * @entity PersonRepresentation
     * @tags staff, tutela, paciente
     */
    public function actionVinculosPacienteParaStaff(): array
    {
        $params = $this->mergedParams();
        $subjectPersonaId = (int) ($params['id_persona'] ?? $params['subject_persona_id'] ?? 0);

        try {
            return (new VerifiedGuardianshipService())->listarVinculosPacienteParaStaff($subjectPersonaId);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * GET|POST /api/v1/person-representation/solicitudes-tutela-pendientes-para-staff
     *
     * UI JSON: bandeja de solicitudes de tutela (régimen A) en estado pending.
     *
     * @action_name Solicitudes de tutela pendientes (staff)
     * @entity PersonRepresentation
     * @tags staff, tutela, ui_json
     */
    public function actionSolicitudesTutelaPendientesParaStaff(): array
    {
        $payload = (new VerifiedGuardianshipService())->listarSolicitudesTutelaPendientesParaStaff();
        $solicitudes = is_array($payload['data']['solicitudes'] ?? null) ? $payload['data']['solicitudes'] : [];
        $total = (int) ($payload['data']['total'] ?? count($solicitudes));

        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post(), [
            'resumen_texto' => $total === 0
                ? 'No hay solicitudes de tutela pendientes de verificación.'
                : $total . ($total === 1 ? ' solicitud pendiente de verificación.' : ' solicitudes pendientes de verificación.'),
        ]);

        $out = UiScreenService::renderUiDefinition(
            'person-representation',
            'solicitudes-tutela-pendientes-para-staff',
            $params,
            null
        );

        $items = [];
        foreach ($solicitudes as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $actor = is_array($row['actor'] ?? null) ? $row['actor'] : [];
            $subject = is_array($row['subject'] ?? null) ? $row['subject'] : [];
            $rel = is_array($row['relationship_type'] ?? null) ? $row['relationship_type'] : [];
            $actorLabel = trim(((string) ($actor['apellido'] ?? '')) . ', ' . ((string) ($actor['nombre'] ?? '')));
            $subjectLabel = trim(((string) ($subject['apellido'] ?? '')) . ', ' . ((string) ($subject['nombre'] ?? '')));
            $doc = trim((string) ($subject['documento'] ?? ''));
            $relLabel = trim((string) ($rel['label'] ?? $rel['code'] ?? 'Tutela'));
            $name = $actorLabel !== ', ' ? $actorLabel : 'Solicitante';
            $subtitle = ($subjectLabel !== ', ' ? $subjectLabel : 'Menor')
                . ($doc !== '' ? ' · DNI ' . $doc : '')
                . ' · ' . $relLabel;

            $items[] = [
                'id' => (string) $id,
                'name' => $name,
                'label' => $name,
                'subtitle' => $subtitle,
                'meta' => [
                    'person_related_id' => $id,
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'created_at' => $row['created_at'] ?? null,
                ],
            ];
        }

        $out['success'] = true;
        $out['data'] = $payload['data'];

        return UiScreenService::withListBlockItems($out, $items, 'solicitudes');
    }

    /**
     * POST /api/v1/person-representation/designar-representante
     *
     * Body: representative_id_persona | representative_documento, relationship_type_code (opcional), permissions (opcional).
     *
     * @action_name Designar representante
     * @entity PersonRepresentation
     * @tags paciente, delegación, representante
     */
    public function actionDesignarRepresentante(): array
    {
        $this->assertPost();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        try {
            return (new PatientDelegationService())->designarRepresentante($idPersona, $this->mergedParams());
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * POST /api/v1/person-representation/revocar-representante
     *
     * Body: person_related_id | representative_id_persona.
     *
     * @action_name Revocar representante
     * @entity PersonRepresentation
     * @tags paciente, delegación, revocación
     */
    public function actionRevocarRepresentante(): array
    {
        $this->assertPost();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        try {
            return (new PatientDelegationService())->revocarRepresentante($idPersona, $this->mergedParams());
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * GET|POST /api/v1/person-representation/mis-representantes
     *
     * @action_name Mis representantes designados
     * @entity PersonRepresentation
     * @tags paciente, delegación
     */
    public function actionMisRepresentantes(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        return (new PatientDelegationService())->listarMisRepresentantes($idPersona);
    }

    /**
     * GET|POST /api/v1/person-representation/pacientes-a-cargo
     *
     * @action_name Pacientes a mi cargo (representante)
     * @entity PersonRepresentation
     * @tags representante, delegación
     */
    public function actionPacientesACargo(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        return (new PatientDelegationService())->listarPacientesACargo($idPersona);
    }

    /**
     * GET|POST /api/v1/person-representation/preferencias-como-paciente
     *
     * GET: lee preferencias. POST: body notify_on_representative_action.
     *
     * @action_name Preferencias de representación (paciente)
     * @entity PersonRepresentation
     * @tags paciente, delegación, notificaciones
     */
    /**
     * POST /api/v1/person-representation/establecer-sujeto-paciente
     *
     * Body: subject_persona_id (0 o omitir para limpiar contexto "a cargo de").
     *
     * @action_name Establecer sujeto paciente en sesión
     * @entity PersonRepresentation
     * @tags paciente, contexto, delegación
     */
    public function actionEstablecerSujetoPaciente(): array
    {
        $this->assertPost();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $params = $this->mergedParams();
        $subjectRaw = $params['subject_persona_id'] ?? $params['id_persona_sujeto'] ?? null;
        $svc = new \common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService();

        if ($subjectRaw === null || $subjectRaw === '' || (int) $subjectRaw <= 0) {
            $svc->establecerSujetoEnSesion(null);

            return [
                'success' => true,
                'data' => [
                    'subject_persona_id' => $idPersona,
                    'mensaje' => 'Contexto restablecido a tu persona.',
                ],
            ];
        }

        $subjectId = (int) $subjectRaw;
        try {
            $svc->establecerSujetoEnSesion($subjectId);
        } catch (\yii\web\ForbiddenHttpException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return [
            'success' => true,
            'data' => [
                'subject_persona_id' => $subjectId,
                'mensaje' => 'Sujeto de atención fijado en sesión.',
            ],
        ];
    }

    public function actionPreferenciasComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        $service = new PatientDelegationService();
        if (Yii::$app->request->isPost) {
            $params = $this->mergedParams();
            if (array_key_exists('notify_on_representative_action', $params)) {
                try {
                    return $service->guardarPreferencias($idPersona, $params);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }
        }

        return $service->obtenerPreferencias($idPersona);
    }

    private function assertPost(): void
    {
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException(['POST']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedParams(): array
    {
        return array_merge(Yii::$app->request->get(), Yii::$app->request->post());
    }
}
