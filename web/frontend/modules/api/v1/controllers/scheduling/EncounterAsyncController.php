<?php

namespace frontend\modules\api\v1\controllers\scheduling;

use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\BadRequestHttpException;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\ListEncounterAsyncInbox;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\AdvanceEncounterAsyncLifecycle;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\SubmitEncounterAsyncRequest;
use common\components\Platform\Ui\UiScreenService;

/**
 * Solicitudes de consulta async (paciente, sin turno).
 *
 * RBAC ApiGhost: /api/encounter-async/&lt;action&gt;
 */
class EncounterAsyncController extends BaseController
{
    /**
     * GET|POST /api/v1/encounter-async/solicitar-como-paciente
     *
     * @action_name Solicitar consulta clínica por mensaje (paciente)
     * @entity EncounterAsync
     * @tags views, ui, paciente, consulta, async
     */
    public function actionSolicitarComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'encounter-async',
            'solicitar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $idPersona = (int) Yii::$app->user->getIdPersona();
                if ($idPersona <= 0) {
                    throw new BadRequestHttpException('Sesión sin persona.');
                }

                $merged = array_merge(Yii::$app->request->get(), $post);

                try {
                    return (new SubmitEncounterAsyncRequest())->solicitarComoPaciente($idPersona, $merged);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }
        );
    }

    /**
     * POST /api/v1/encounter-async/tomar-como-staff
     *
     * @action_name Tomar solicitud async (staff)
     * @entity EncounterAsync
     */
    public function actionTomarComoStaff(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = (int) ($body['encounter_id'] ?? $body['id'] ?? 0);
        if ($encounterId <= 0) {
            throw new BadRequestHttpException('encounter_id requerido.');
        }

        try {
            return (new ListEncounterAsyncInbox())->tomarComoStaff($encounterId);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * POST /api/v1/encounter-async/cancelar-como-paciente
     *
     * @action_name Cancelar solicitud async (paciente)
     */
    public function actionCancelarComoPaciente(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = (int) ($body['encounter_id'] ?? $body['id'] ?? 0);
        if ($encounterId <= 0) {
            throw new BadRequestHttpException('encounter_id requerido.');
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        try {
            return (new AdvanceEncounterAsyncLifecycle())->cancelarComoPaciente($encounterId, $idPersona);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * POST /api/v1/encounter-async/cerrar-como-staff
     *
     * @action_name Cerrar solicitud async con resolución (staff)
     */
    public function actionCerrarComoStaff(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = (int) ($body['encounter_id'] ?? $body['id'] ?? 0);
        $resolution = trim((string) ($body['resolution_code'] ?? $body['resolucion'] ?? ''));
        $note = isset($body['note']) ? trim((string) $body['note']) : null;
        if ($encounterId <= 0) {
            throw new BadRequestHttpException('encounter_id requerido.');
        }
        if ($resolution === '') {
            throw new BadRequestHttpException('resolution_code requerido.');
        }

        try {
            return (new AdvanceEncounterAsyncLifecycle())->cerrarComoStaff($encounterId, $resolution, $note);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
