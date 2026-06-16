<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Ui\UiScreenService;
use common\components\Platform\Core\Service\QuejaPacienteService;

/**
 * Quejas operativas de pacientes (app móvil).
 *
 * RBAC ApiGhost: /api/queja-paciente/&lt;action&gt;
 */
class QuejaPacienteController extends BaseController
{
    /**
     * GET|POST /api/v1/queja-paciente/enviar-como-paciente
     *
     * @action_name Enviar queja (paciente)
     * @entity QuejaPaciente
     * @tags views, ui, paciente, queja, plataforma
     */
    public function actionEnviarComoPaciente(): array
    {
        $req = Yii::$app->request;

        return UiScreenService::handleScreen(
            'queja-paciente',
            'enviar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $idPersona = (int) Yii::$app->user->getIdPersona();
                if ($idPersona <= 0) {
                    throw new BadRequestHttpException('Sesión sin persona.');
                }

                try {
                    return (new QuejaPacienteService())->enviarComoPaciente($idPersona, $post);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }
        );
    }
}
