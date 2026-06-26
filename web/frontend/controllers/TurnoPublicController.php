<?php

namespace frontend\controllers;

use common\components\Domain\Scheduling\Service\TurnoResolucionLinkTokenService;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Páginas públicas de turnos (token firmado, sin sesión).
 */
class TurnoPublicController extends Controller
{
    public $layout = '@frontend/views/layouts/loginLayout.php';

    /**
     * Link firmado desde email/SMS: reubicar turno en resolución.
     */
    public function actionResolucion(string $token)
    {
        $parsed = (new TurnoResolucionLinkTokenService())->verify($token);
        if ($parsed === null) {
            throw new NotFoundHttpException('El enlace expiró o no es válido.');
        }

        /** @var TurnoResolucion|null $res */
        $res = TurnoResolucion::find()
            ->where([
                'id' => (int) $parsed['id_resolucion'],
                'estado' => TurnoResolucion::ESTADO_PENDIENTE,
            ])
            ->one();
        if ($res === null) {
            throw new NotFoundHttpException('Este turno ya fue resuelto o cancelado.');
        }

        $turno = Turno::findActive()->andWhere([
            'id_turnos' => (int) $res->id_turno,
            'id_persona' => (int) $parsed['id_persona'],
            'estado' => Turno::ESTADO_EN_RESOLUCION,
        ])->one();
        if ($turno === null) {
            throw new NotFoundHttpException('Turno no disponible para reubicación.');
        }

        $appUrl = (string) (Yii::$app->params['turnoResolucionMulticanal']['app_deep_link'] ?? '/');

        return $this->render('resolucion', [
            'turno' => $turno,
            'resolucion' => $res,
            'appUrl' => $appUrl,
        ]);
    }
}
