<?php

namespace frontend\modules\api\v1\controllers;

use common\models\ConsultaChatMessage;
use common\components\Domain\Clinical\Service\SecureMediaService;
use common\models\Clinical\Encounter;
use frontend\modules\api\v1\controllers\clinical\ClinicalAccessTrait;
use Yii;
use yii\web\UploadedFile;

/**
 * API chat clínico por encounter (mensajes, envío, subida, estado).
 *
 * La URL conserva el segmento `consulta-chat`; el identificador es `encounter_id`.
 */
class ConsultaChatController extends BaseController
{
    use ClinicalAccessTrait;

    public $enableCsrfValidation = false;

    /**
     * GET /api/v1/consulta-chat/mensajes/{id} — {id} = encounter_id.
     */
    public function actionListarMensajes($id)
    {
        $encounterId = (int) $id;
        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }

        $messages = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $formattedMessages = [];
        foreach ($messages as $message) {
            $content = $message->content;
            if (in_array($message->message_type, ['imagen', 'audio', 'video', 'documento'], true) && $content !== '') {
                $content = SecureMediaService::contentForApi(
                    SecureMediaService::SCOPE_CONSULTA_CHAT,
                    $encounterId,
                    (string) $content
                );
            }
            $formattedMessages[] = [
                'id' => $message->id,
                'content' => $content,
                'user_id' => $message->user_id,
                'user_name' => $message->user_name,
                'user_role' => $message->user_role,
                'message_type' => $message->message_type ?: 'texto',
                'created_at' => $message->created_at,
                'is_read' => $message->is_read,
            ];
        }

        $subject = $encounter->subject;

        return [
            'success' => true,
            'message' => 'Mensajes obtenidos exitosamente',
            'data' => [
                'messages' => $formattedMessages,
                'encounter_id' => (int) $encounter->id,
                'consulta_id' => (int) $encounter->id,
                'encounter' => [
                    'id' => (int) $encounter->id,
                    'paciente' => $subject ? $subject->getNombreCompleto() : 'Paciente',
                ],
            ],
        ];
    }

    public function actionEnviar()
    {
        $body = Yii::$app->request->getBodyParams();
        $encounterId = $this->resolveEncounterIdFromBody($body);
        if (!$encounterId) {
            return ['success' => false, 'message' => 'Datos requeridos: encounter_id (o consulta_id), message', 'data' => null];
        }

        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }

        $message = $body['message'] ?? null;
        $user_role = $body['user_role'] ?? null;
        if ($message === null || $message === '') {
            return ['success' => false, 'message' => 'Datos requeridos: encounter_id, message', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Usuario';
        if (!$user_role) {
            $user_role = (int) $encounter->subject_persona_id === (int) Yii::$app->user->getIdPersona() ? 'paciente' : 'medico';
        }

        $messageType = $body['message_type'] ?? 'texto';
        $allowedTypes = ['texto', 'imagen', 'audio', 'video', 'documento'];
        if (!in_array($messageType, $allowedTypes, true)) {
            $messageType = 'texto';
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = $encounterId;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = $user_role;
        $chatMessage->content = $message;
        $chatMessage->message_type = $messageType;

        if (!$chatMessage->save()) {
            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $chatMessage->getFirstErrors()),
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Mensaje enviado exitosamente',
            'data' => [
                'id' => $chatMessage->id,
                'content' => $chatMessage->content,
                'user_id' => $chatMessage->user_id,
                'user_name' => $chatMessage->user_name,
                'user_role' => $chatMessage->user_role,
                'message_type' => $chatMessage->message_type,
                'created_at' => $chatMessage->created_at,
                'is_read' => $chatMessage->is_read,
            ],
        ];
    }

    public function actionSubir()
    {
        $encounterId = $this->resolveEncounterIdFromBody(Yii::$app->request->post());
        if (!$encounterId) {
            return ['success' => false, 'message' => 'Falta encounter_id (o consulta_id)', 'data' => null];
        }

        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }

        $messageType = Yii::$app->request->post('message_type', 'imagen');
        if (!in_array($messageType, self::UPLOAD_MESSAGE_TYPES, true)) {
            return ['success' => false, 'message' => 'message_type debe ser: imagen, audio, video o documento', 'data' => null];
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file || !$file->tempName) {
            return ['success' => false, 'message' => 'Debe enviar un archivo en el campo "file"', 'data' => null];
        }

        $ext = $file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION);
        if (!$ext) {
            $ext = 'bin';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/consulta_chat/' . $encounterId);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);

                return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
            }
        }

        $relativePath = 'uploads/consulta_chat/' . $encounterId . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!$file->saveAs($fullPath)) {
            return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $userName = Yii::$app->user->identity->username ?? 'Usuario';
        $user_role = (int) $encounter->subject_persona_id === (int) Yii::$app->user->getIdPersona() ? 'paciente' : 'medico';

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->encounter_id = $encounterId;
        $chatMessage->user_id = $userId;
        $chatMessage->user_name = $userName;
        $chatMessage->user_role = $user_role;
        $chatMessage->content = $relativePath;
        $chatMessage->message_type = $messageType;

        if (!$chatMessage->save()) {
            @unlink($fullPath);

            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $chatMessage->getFirstErrors()),
                'data' => null,
            ];
        }

        $contentUrl = SecureMediaService::absoluteApiUrl(
            SecureMediaService::SCOPE_CONSULTA_CHAT,
            $encounterId,
            $relativePath
        );

        return [
            'success' => true,
            'message' => 'Archivo enviado exitosamente',
            'data' => [
                'id' => $chatMessage->id,
                'content' => $contentUrl,
                'user_id' => $chatMessage->user_id,
                'user_name' => $chatMessage->user_name,
                'user_role' => $chatMessage->user_role,
                'message_type' => $chatMessage->message_type,
                'created_at' => $chatMessage->created_at,
                'is_read' => $chatMessage->is_read,
            ],
        ];
    }

    public function actionEstado($id)
    {
        $encounterId = (int) $id;
        [$encounter, $err] = $this->requireEncounterAccess($encounterId);
        if ($err !== null) {
            return $err;
        }

        $unreadCount = ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId, 'is_read' => false])
            ->count();

        return [
            'success' => true,
            'message' => 'Estado obtenido exitosamente',
            'data' => [
                'encounter_id' => $encounterId,
                'consulta_id' => $encounterId,
                'unread_count' => $unreadCount,
                'is_online' => true,
                'last_activity' => $encounter->updated_at ?? $encounter->created_at,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function resolveEncounterIdFromBody(array $body): int
    {
        $id = $body['encounter_id'] ?? $body['consulta_id'] ?? null;

        return $id ? (int) $id : 0;
    }

    private const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio', 'video', 'documento'];
}
