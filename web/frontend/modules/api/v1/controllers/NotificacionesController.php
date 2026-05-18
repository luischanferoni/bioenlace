<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Services\Notificaciones\PersonaNotificacionService;

/**
 * Bandeja de alertas del usuario autenticado (paciente / cualquier persona con JWT).
 */
class NotificacionesController extends BaseController
{
    /**
     * GET|POST /api/v1/notificaciones/listar-como-paciente
     * Query/body: solo_no_leidas (0|1), limit, offset
     */
    public function actionListarComoPaciente(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $soloNoLeidas = !empty($params['solo_no_leidas']) && (string) $params['solo_no_leidas'] !== '0';
        $limit = isset($params['limit']) && $params['limit'] !== '' ? (int) $params['limit'] : 30;
        $offset = isset($params['offset']) && $params['offset'] !== '' ? (int) $params['offset'] : 0;

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }

        return [
            'success' => true,
            'data' => PersonaNotificacionService::listarParaPersona($idPersona, $soloNoLeidas, $limit, $offset),
        ];
    }

    /**
     * POST /api/v1/notificaciones/marcar-leida-como-paciente
     * Body: id (opcional; si falta, marca todas)
     */
    public function actionMarcarLeidaComoPaciente(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new \yii\web\MethodNotAllowedHttpException(['POST']);
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Sesión sin persona.');
        }
        $post = array_merge($req->get(), $req->post());
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        if ($id > 0) {
            PersonaNotificacionService::marcarLeida($idPersona, $id);
        } else {
            PersonaNotificacionService::marcarTodasLeidas($idPersona);
        }

        return [
            'success' => true,
            'data' => PersonaNotificacionService::listarParaPersona($idPersona, false, 1, 0),
        ];
    }
}
