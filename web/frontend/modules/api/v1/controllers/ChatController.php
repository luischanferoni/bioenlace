<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Platform\Assistant\Chat\ChatOrchestrator;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Core\Db\BioenlaceDb;
use common\models\AsistenteConversacion;
use common\models\AsistenteInteraccion;

/**
 * Asistente conversacional vía {@see ChatOrchestrator}.
 *
 * - GET/OPTIONS `asistente/estado` — estado ligero para clientes.
 * - POST/OPTIONS `asistente/enviar` — sobre en raíz (`kind`: message | interactive | flow).
 *   Request: `content`, `action_id`, o modo flow (`intent_id`, `subintent_id`, `draft`, `interaction`).
 *   HTTP 200: payload del sobre (sin wrapper `{ success, message, data }`).
 *   HTTP 400: errores del motor (`success: false` interno, no convertido a sobre).
 */
class ChatController extends BaseController
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return parent::behaviors();
    }

    public function actionEstado()
    {
        return ['success' => true, 'mensajes' => [], 'msj' => ''];
    }

    public function actionRecibir()
    {
        $body = Yii::$app->request->getBodyParams();
        $body = is_array($body) ? $body : [];

        $intentId = isset($body['intent_id']) ? trim((string) $body['intent_id']) : '';
        $content = isset($body['content']) ? trim((string) $body['content']) : '';
        $actionId = $body['action_id'] ?? null;
        if ($actionId !== null && $actionId !== '') {
            $actionId = (string) $actionId;
        } else {
            $actionId = null;
        }

        if ($intentId === '' && $content === '' && $actionId === null) {
            return $this->error('Se requiere content o action_id (o intent_id)', null, 400);
        }

        $userId = (int) Yii::$app->user->id;
        $uidStr = (string) $userId;

        if (isset($body['senderId']) && (string) $body['senderId'] !== $uidStr) {
            return $this->error('senderId no coincide con el usuario autenticado', null, 403);
        }

        if ($intentId === '') {
            $textoPersistUsuario = $content !== ''
                ? $content
                : ($actionId !== null ? '[action_id:' . $actionId . ']' : ' ');

            $conversacion = AsistenteConversacion::findOne(['usuario_id' => $uidStr, 'bot_id' => 'BOT']);
            if (!$conversacion) {
                $conversacion = new AsistenteConversacion([
                    'usuario_id' => $uidStr,
                    'bot_id' => 'BOT',
                ]);
                if (!$conversacion->save()) {
                    Yii::error('No se pudo crear asistente_conversacion: ' . json_encode($conversacion->errors), 'asistente');
                    return $this->error('No se pudo registrar la conversación', null, 500);
                }
            }

            $interaccionUsuario = new AsistenteInteraccion([
                'conversacion_id' => $conversacion->id,
                'sender_id' => $uidStr,
                'sender_name' => $uidStr,
                'texto' => $textoPersistUsuario,
                'status' => 'recibido',
                'message_type' => 'texto',
                'is_resent' => !empty($body['isResent']),
            ]);
            if (!$interaccionUsuario->save()) {
                Yii::error('No se pudo guardar interacción usuario: ' . json_encode($interaccionUsuario->errors), 'asistente');
            }
        }

        try {
            $out = ChatOrchestrator::handle($body, $userId);
        } catch (\Throwable $e) {
            Yii::error(
                'ChatOrchestrator en asistente/enviar: ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine(),
                'asistente'
            );
            Yii::error($e->getTraceAsString(), 'asistente');
            return $this->error('Error al procesar la consulta', null, 500);
        }

        if (!AssistantEnvelope::isPublicEnvelope($out) && empty($out['success'])) {
            $err = isset($out['error']) ? (string) $out['error'] : 'Error';
            return $this->error($err, $out, 400);
        }

        BioenlaceDb::ensureConnection();

        if ($intentId === '') {
            $replyText = ChatOrchestrator::botReplyTextForPersistence($out);

            $conversacion = AsistenteConversacion::findOne(['usuario_id' => $uidStr, 'bot_id' => 'BOT']);
            if ($conversacion) {
                $interaccionBot = new AsistenteInteraccion([
                    'conversacion_id' => $conversacion->id,
                    'sender_id' => 'BOT',
                    'sender_name' => 'Bot',
                    'texto' => $replyText,
                    'status' => 'enviado',
                    'message_type' => 'texto',
                    'is_resent' => 0,
                ]);
                if (!$interaccionBot->save()) {
                    Yii::error('No se pudo guardar interacción bot: ' . json_encode($interaccionBot->errors), 'asistente');
                }
            }
        }

        return $out;
    }
}
