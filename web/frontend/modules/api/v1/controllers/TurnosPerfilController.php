<?php

namespace frontend\modules\api\v1\controllers;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileViewService;
use Yii;

/**
 * Perfil factual de turnos.
 *
 * GET historial-propio-como-paciente: devuelve únicamente el perfil del titular autenticado.
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
}
