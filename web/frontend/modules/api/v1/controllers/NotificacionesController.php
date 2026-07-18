<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Platform\Core\Service\Notificaciones\PersonaNotificacionService;
use common\components\Platform\Core\Service\Notificaciones\PersonaNotificacionInteractionService;
use common\models\TurnoEventoAudit;

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
     * POST /api/v1/notificaciones/marcar-leida
     * Body: id (opcional; si falta, marca todas)
     */
    public function actionMarcarLeida(): array
    {
        return $this->marcarLeidaResponse();
    }

    /**
     * POST /api/v1/notificaciones/registrar-interaccion-push-propia
     * Body: notification_ref, interaction_type (DELIVERED|OPENED), client_event_id,
     *       source?, provider_message_id?, occurred_at?, actor_type?
     *
     * No confía en ids de dominio del cliente; el servidor deriva turno/persona del sobre.
     */
    public function actionRegistrarInteraccionPushPropia(): array
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
        $body = $req->getBodyParams();
        if (is_array($body)) {
            $post = array_merge($post, $body);
        }

        $actorType = isset($post['actor_type']) ? (string) $post['actor_type'] : null;
        if ($actorType !== null && !in_array($actorType, TurnoEventoAudit::actorTypeValues(), true)) {
            $actorType = null;
        }

        try {
            $result = (new PersonaNotificacionInteractionService())->registerOwn(
                $idPersona,
                [
                    'notification_ref' => (string) ($post['notification_ref'] ?? ''),
                    'interaction_type' => (string) ($post['interaction_type'] ?? ''),
                    'client_event_id' => (string) ($post['client_event_id'] ?? ''),
                    'source' => $post['source'] ?? null,
                    'provider_message_id' => $post['provider_message_id'] ?? null,
                    'occurred_at' => $post['occurred_at'] ?? null,
                    'actor_type' => $actorType,
                ],
                Yii::$app->user->id ? (int) Yii::$app->user->id : null
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return [
            'success' => true,
            'data' => $result,
        ];
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
