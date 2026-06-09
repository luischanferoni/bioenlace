<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use common\components\Person\Representation\Service\VerifiedGuardianshipService;

/**
 * Representación operativa paciente — régimen A (tutela verificada) y transiciones staff compartidas.
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
