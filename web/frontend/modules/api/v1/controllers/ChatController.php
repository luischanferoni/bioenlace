<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\IntentEngine\IntentEngine;
use common\components\SubIntentEngine\SubIntentEngine;
use common\models\AsistenteConversacion;
use common\models\AsistenteInteraccion;

/**
 * Asistente conversacional: pipeline unificado vía {@see IntentEngine}.
 *
 * - GET/OPTIONS `asistente/estado` — estado ligero para clientes que consultan historial vacío/plantilla.
 * - POST/OPTIONS `asistente/enviar` — contrato único:
 *   - Root (IntentEngine): `content` o `action_id`.
 *   - Dentro de intent (SubIntentEngine): `intent_id`, `subintent_id`, `draft`, y `content` o `interaction`.
 *   Identidad: usuario autenticado (Yii). `senderId` opcional; si se envía, debe coincidir con el usuario.
 *   Respuesta estándar v1: `success`, `message`, `data` (payload del agente), HTTP 200.
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
        $intentId = isset($body['intent_id']) ? trim((string) $body['intent_id']) : '';

        // Modo intent (SubIntentEngine): no hay retrocompatibilidad/legacy aquí.
        if ($intentId !== '') {
            $userId = (int) Yii::$app->user->id;
            try {
                $out = SubIntentEngine::process(is_array($body) ? $body : [], $userId);
            } catch (\Throwable $e) {
                Yii::error('SubIntentEngine en asistente/enviar: ' . $e->getMessage(), 'asistente');
                return $this->error('Error al procesar el intent', null, 500);
            }

            if (empty($out['success'])) {
                $err = isset($out['error']) ? (string) $out['error'] : 'Error';
                return $this->error($err, $out, 400);
            }

            // Contrato chat v2: no envolver en {success,message,data}; devolver payload del motor directamente.
            // (Evita doble `success`, `message` genérico, y deja `kind` en raíz del payload.)
            return $out;
        }

        $content = isset($body['content']) ? trim((string) $body['content']) : '';
        $actionId = $body['action_id'] ?? null;
        if ($actionId !== null && $actionId !== '') {
            $actionId = (string) $actionId;
        } else {
            $actionId = null;
        }

        if ($content === '' && $actionId === null) {
            return $this->error('Se requiere content o action_id (o intent_id)', null, 400);
        }

        $userId = Yii::$app->user->id;
        $uidStr = (string) $userId;

        if (isset($body['senderId']) && (string) $body['senderId'] !== $uidStr) {
            return $this->error('senderId no coincide con el usuario autenticado', null, 403);
        }

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

        try {
            $agentResult = IntentEngine::processQuery($content, (int) $userId, $actionId);
        } catch (\Throwable $e) {
            Yii::error('IntentEngine en asistente/enviar: ' . $e->getMessage(), 'asistente');
            return $this->error('Error al procesar la consulta', null, 500);
        }

        $replyText = (string) ($agentResult['explanation'] ?? $agentResult['error'] ?? 'Consulta procesada');

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

        // Contrato chat v2: devolver payload directo (sin wrapper).
        return $agentResult;
    }
}
