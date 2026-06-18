<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Domain\Scheduling\Service\ConsultaAsyncSolicitudService;
use common\components\Platform\Ui\UiScreenService;

/**
 * Solicitudes de consulta async (paciente, sin turno).
 *
 * RBAC ApiGhost: /api/consulta-async/&lt;action&gt;
 */
class ConsultaAsyncController extends BaseController
{
    /**
     * GET|POST /api/v1/consulta-async/solicitar-como-paciente
     *
     * @action_name Solicitar consulta por mensaje (paciente)
     * @entity ConsultaAsync
     * @tags views, ui, paciente, consulta, async
     */
    public function actionSolicitarComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'consulta-async',
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
                    return (new ConsultaAsyncSolicitudService())->solicitarComoPaciente($idPersona, $merged);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }
        );
    }
}
