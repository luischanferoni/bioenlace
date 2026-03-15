<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use common\models\ConsultaMotivosMessage;
use common\models\Consulta;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Chat de motivos de consulta (paciente envía texto, audio, fotos).
 */
class ConsultaMotivosChatController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
        ];
    }

    public $freeAccessActions = ['messages', 'send', 'upload'];

    /**
     * Indica si el usuario actual puede acceder a la consulta (paciente o médico asignado).
     */
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

    /**
     * GET mensajes de motivos de una consulta.
     */
    public function actionMessages($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        list($consulta, $err) = $this->requireConsultaAccess($id);
        if ($err !== null) {
            return $err;
        }

        $messages = ConsultaMotivosMessage::find()
            ->where(['consulta_id' => $id])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $formattedMessages = [];
        foreach ($messages as $message) {
            $content = $message->content;
            if (in_array($message->message_type, ['imagen', 'audio'], true) && $content !== '' && strpos($content, 'http') !== 0) {
                $content = rtrim($baseUrl, '/') . '/' . ltrim($content, '/');
            }
            $formattedMessages[] = [
                'id' => $message->id,
                'content' => $content,
                'user_id' => $message->user_id,
                'user_name' => $message->user_name,
                'message_type' => $message->message_type ?: 'texto',
                'created_at' => $message->created_at,
            ];
        }

        return [
            'success' => true,
            'message' => 'Mensajes obtenidos exitosamente',
            'data' => [
                'messages' => $formattedMessages,
                'consulta_id' => (int) $id,
            ],
        ];
    }

    /**
     * POST enviar mensaje de texto.
     */
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
        if ($message === null || $message === '') {
            return ['success' => false, 'message' => 'El mensaje no puede estar vacío', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        $userName = $user ? $user->username : 'Paciente';

        $msg = new ConsultaMotivosMessage();
        $msg->consulta_id = (int) $consulta_id;
        $msg->user_id = $userId;
        $msg->user_name = $userName;
        $msg->content = $message;
        $msg->message_type = ConsultaMotivosMessage::TYPE_TEXTO;

        if (!$msg->save()) {
            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Mensaje enviado exitosamente',
            'data' => [
                'id' => $msg->id,
                'content' => $msg->content,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user_name,
                'message_type' => $msg->message_type,
                'created_at' => $msg->created_at,
            ],
        ];
    }

    const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio'];

    /**
     * POST subir archivo (imagen o audio).
     */
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
            return ['success' => false, 'message' => 'message_type debe ser: imagen o audio', 'data' => null];
        }

        $file = UploadedFile::getInstanceByName('file');
        if (!$file || !$file->tempName) {
            return ['success' => false, 'message' => 'Debe enviar un archivo en el campo "file"', 'data' => null];
        }

        $ext = $file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION);
        if (!$ext) {
            $ext = $messageType === 'audio' ? 'm4a' : 'jpg';
        }
        $filename = sprintf('%s_%s.%s', date('YmdHis'), uniqid(), $ext);

        $basePath = Yii::getAlias('@frontend/web/uploads/motivos_consulta/' . (int) $consulta_id);
        if (!is_dir($basePath)) {
            if (!@mkdir($basePath, 0755, true)) {
                Yii::error('No se pudo crear directorio: ' . $basePath);
                return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
            }
        }

        $relativePath = 'uploads/motivos_consulta/' . (int) $consulta_id . '/' . $filename;
        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relativePath;

        if (!$file->saveAs($fullPath)) {
            return ['success' => false, 'message' => 'Error al guardar el archivo', 'data' => null];
        }

        $userId = Yii::$app->user->id;
        $user = \webvimark\modules\UserManagement\models\User::findOne($userId);
        $userName = $user ? $user->username : 'Paciente';

        $msg = new ConsultaMotivosMessage();
        $msg->consulta_id = (int) $consulta_id;
        $msg->user_id = $userId;
        $msg->user_name = $userName;
        $msg->content = $relativePath;
        $msg->message_type = $messageType;

        if (!$msg->save()) {
            @unlink($fullPath);
            return [
                'success' => false,
                'message' => 'Error guardando mensaje: ' . implode(', ', $msg->getFirstErrors()),
                'data' => null,
            ];
        }

        $baseUrl = Yii::$app->request->hostInfo . (Yii::getAlias('@web') ?: '');
        $contentUrl = rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');

        return [
            'success' => true,
            'message' => 'Archivo enviado exitosamente',
            'data' => [
                'id' => $msg->id,
                'content' => $contentUrl,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user_name,
                'message_type' => $msg->message_type,
                'created_at' => $msg->created_at,
            ],
        ];
    }
}
