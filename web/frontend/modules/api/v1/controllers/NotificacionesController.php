<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Core\Service\Notificaciones\PersonaNotificacionService;

/**
 * Bandeja de alertas del usuario autenticado (persona del JWT).
 *
 * El filtrado por contexto (web staff vs móvil paciente) lo resuelve {@see PersonaNotificacionService}.
 */
class NotificacionesController extends BaseController
{
    /**
     * GET|POST /api/v1/notificaciones/listar
     * Query/body: solo_no_leidas (0|1), limit, offset
     *
     * @action_name Bandeja de alertas
     * @entity Notificaciones
     * @tags alertas, notificaciones, bandeja
     */
    public function actionListar(): array
    {
        return $this->listarResponse();
    }

    /**
     * @deprecated Usar {@see actionListar()}. Alias móvil legacy.
     */
    public function actionListarComoPaciente(): array
    {
        return $this->listarResponse();
    }

    /**
     * POST /api/v1/notificaciones/marcar-leida
     * Body: id (opcional; si falta, marca todas)
     */
    public function actionMarcarLeida(): array
    {
        return $this->marcarLeidaResponse();
    }

    /**
     * @deprecated Usar {@see actionMarcarLeida()}. Alias móvil legacy.
     */
    public function actionMarcarLeidaComoPaciente(): array
    {
        return $this->marcarLeidaResponse();
    }

    /**
     * @return array{success: true, data: array<string, mixed>}
     */
    private function listarResponse(): array
    {
        [$soloNoLeidas, $limit, $offset] = $this->parseListParams();

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
     * @return array{success: true, data: array<string, mixed>}
     */
    private function marcarLeidaResponse(): array
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

    /**
     * @return array{0: bool, 1: int, 2: int}
     */
    private function parseListParams(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $soloNoLeidas = !empty($params['solo_no_leidas']) && (string) $params['solo_no_leidas'] !== '0';
        $limit = isset($params['limit']) && $params['limit'] !== '' ? (int) $params['limit'] : 30;
        $offset = isset($params['offset']) && $params['offset'] !== '' ? (int) $params['offset'] : 0;

        return [$soloNoLeidas, $limit, $offset];
    }
}
