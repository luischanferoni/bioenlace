<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use common\models\ConsultaChatMessage;
use common\models\Consulta;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Chat médico–paciente por consulta.
 */
class ConsultaChatController extends Controller
{
    public $enableCsrfValidation = false;

    const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio', 'video', 'documento'];

    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
        ];
    }

    public $freeAccessActions = ['messages', 'send', 'upload', 'status'];

    protected function canAccessConsulta(Consulta $consulta)
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return false;
        }
        if ((int) $consulta->id_persona === (int) $persona->id_persona) {
            return true;
        }
        $rrhhEfector = RrhhEfector::find()->where(['id_rr_hh' => $consulta->id_rr_hh])->one();
        return $rrhhEfector && (int) $rrhhEfector->id_persona === (int) $persona->id_persona;
    }

    /**
     * Obtiene la consulta y verifica que el usuario tenga acceso. Retorna [consulta, null] o [null, array error].
     */
    protected function requireConsultaAccess($consulta_id)
    {
        $consulta = Consulta::findOne($consulta_id);
        if (!$consulta) {
            Yii::$app->response->statusCode = 404;
            return [null, ['success' => false, 'message' => 'Consulta no encontrada', 'data' => null]];
        }
        if (!$this->canAccessConsulta($consulta)) {
            Yii::$app->response->statusCode = 403;
            return [null, ['success' => false, 'message' => 'No tiene permiso para acceder a esta consulta', 'data' => null]];
        }
        return [$consulta, null];
    }

    public function actionMessages($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        list($consulta, $err) = $this->requireConsultaAccess($id);
        if ($err !== null) {
            return $err;
        }

        $messages = ConsultaChatMessage::find()
            ->where(['consulta_id' => $id])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $formattedMessages = [];
        foreach ($messages as $message) {
            $content = $message->content;
            if (in_array($message->message_type, ['imagen', 'audio', 'video', 'documento'], true) && $content !== '' && strpos($content, 'http') !== 0) {
                $content = rtrim($baseUrl, '/') . '/' . ltrim($content, '/');
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

        return [
            'success' => true,
            'message' => 'Mensajes obtenidos exitosamente',
            'data' => [
                'messages' => $formattedMessages,
                'consulta' => [
                    'id' => $consulta->id_consulta,
                    'paciente' => $consulta->paciente ? $consulta->paciente->getNombreCompleto() : 'Paciente',
                ],
            ],
        ];
    }

    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $body = Yii::$app->request->getBodyParams();
        $consulta_id = $body['consulta_id'] ?? null;
        if (!$consulta_id) {
            return ['success' => false, 'message' => 'Datos requeridos: consulta_id, message', 'data' => null];
        }

        list($consulta, $err) = $this->requireConsultaAccess($consulta_id);
        if ($err !== null) {
            return $err;
        }

        $message = $body['message'] ?? null;
        $user_role = $body['user_role'] ?? null;
        if ($message === null || $message === '') {
            return ['success' => false, 'message' => 'Datos requeridos: consulta_id, message', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        $userName = $user ? $user->username : 'Usuario';
        if (!$user_role) {
            $persona = Persona::findOne(['id_user' => $userId]);
            $user_role = ($persona && (int) $consulta->id_persona === (int) $persona->id_persona) ? 'paciente' : 'medico';
        }

        $messageType = $body['message_type'] ?? 'texto';
        $allowedTypes = ['texto', 'imagen', 'audio', 'video', 'documento'];
        if (!in_array($messageType, $allowedTypes, true)) {
            $messageType = 'texto';
        }

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->consulta_id = (int) $consulta_id;
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

    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $consulta_id = Yii::$app->request->post('consulta_id');
        if (!$consulta_id) {
            return ['success' => false, 'message' => 'Falta consulta_id', 'data' => null];
        }

        list($consulta, $err) = $this->requireConsultaAccess($consulta_id);
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

        $basePath = Yii::getAlias('@frontend/web/uploads/consulta_chat/' . (int) $consulta_id);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);
                return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
            }
        }

        $relativePath = 'uploads/consulta_chat/' . (int) $consulta_id . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!$file->saveAs($fullPath)) {
            return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        $userName = $user ? $user->username : 'Usuario';
        $persona = Persona::findOne(['id_user' => $userId]);
        $user_role = ($persona && (int) $consulta->id_persona === (int) $persona->id_persona) ? 'paciente' : 'medico';

        $chatMessage = new ConsultaChatMessage();
        $chatMessage->consulta_id = (int) $consulta_id;
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

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $contentUrl = rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');

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

    public function actionStatus($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        list($consulta, $err) = $this->requireConsultaAccess($id);
        if ($err !== null) {
            return $err;
        }

        $unreadCount = ConsultaChatMessage::find()
            ->where(['consulta_id' => $id, 'is_read' => false])
            ->count();

        return [
            'success' => true,
            'message' => 'Estado obtenido exitosamente',
            'data' => [
                'consulta_id' => $id,
                'unread_count' => $unreadCount,
                'is_online' => true,
                'last_activity' => $consulta->updated_at ?? $consulta->created_at,
            ],
        ];
    }
}
