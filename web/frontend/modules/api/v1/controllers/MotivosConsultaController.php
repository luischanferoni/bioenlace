<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;
use yii\web\UploadedFile;
use common\models\ConsultaMotivosMessage;
use common\models\Consulta;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * API para la conversación de motivos de consulta (paciente envía texto, audio, fotos).
 * Los mensajes se almacenan en bruto; un proceso posterior codifica y estructura.
 */
class MotivosConsultaController extends BaseController
{
    public $modelClass = '';
    public $enableCsrfValidation = false;
    protected $_verbs = ['GET', 'POST', 'OPTIONS'];

    const UPLOAD_MESSAGE_TYPES = ['imagen', 'audio'];

    public function behaviors()
    {
        return parent::behaviors();
    }

    protected function canAccessConsulta(Consulta $consulta, $userId)
    {
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

    protected function requireConsultaAccess($consulta_id)
    {
        $err = $this->requerirAutenticacion();
        if ($err !== null) {
            return $err;
        }
        $auth = $this->verificarAutenticacion();
        $userId = $auth['userId'];
        $consulta = Consulta::findOne($consulta_id);
        if (!$consulta) {
            Yii::$app->response->statusCode = 404;
            return ['success' => false, 'message' => 'Consulta no encontrada', 'data' => null];
        }
        if (!$this->canAccessConsulta($consulta, $userId)) {
            Yii::$app->response->statusCode = 403;
            return ['success' => false, 'message' => 'No tiene permiso para acceder a esta consulta', 'data' => null];
        }
        return ['userId' => $userId, 'consulta' => $consulta];
    }

    public function actionOptions()
    {
        if (Yii::$app->getRequest()->getMethod() !== 'OPTIONS') {
            Yii::$app->getResponse()->setStatusCode(405);
        }
        Yii::$app->getResponse()->getHeaders()->set('Allow', implode(', ', $this->_verbs));
    }

    /**
     * GET mensajes de motivos de una consulta
     */
    public function actionMessages($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $access = $this->requireConsultaAccess($consulta_id);
        if (isset($access['success'])) {
            return $access;
        }
        $consulta = $access['consulta'];

        try {
            $messages = ConsultaMotivosMessage::find()
                ->where(['consulta_id' => $consulta_id])
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
                    'consulta_id' => (int) $consulta_id,
                ],
            ];
        } catch (\Exception $e) {
            Yii::error('Error obteniendo mensajes motivos: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del servidor',
                'data' => null,
            ];
        }
    }

    /**
     * POST enviar mensaje de texto
     */
    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $body = Yii::$app->request->getBodyParams();
        $consulta_id = $body['consulta_id'] ?? null;
        if (!$consulta_id) {
            return ['success' => false, 'message' => 'Datos requeridos: consulta_id, message', 'data' => null];
        }

        $access = $this->requireConsultaAccess($consulta_id);
        if (isset($access['success'])) {
            return $access;
        }
        $userId = $access['userId'];
        $consulta = $access['consulta'];

        $message = $body['message'] ?? null;
        if ($message === null || $message === '') {
            return ['success' => false, 'message' => 'El mensaje no puede estar vacío', 'data' => null];
        }

        $user = \common\models\User::findOne($userId);
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

    /**
     * POST subir archivo (imagen o audio)
     */
    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $consulta_id = Yii::$app->request->post('consulta_id');
        if (!$consulta_id) {
            return ['success' => false, 'message' => 'Falta consulta_id', 'data' => null];
        }

        $access = $this->requireConsultaAccess($consulta_id);
        if (isset($access['success'])) {
            return $access;
        }
        $userId = $access['userId'];
        $consulta = $access['consulta'];

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

        $user = \common\models\User::findOne($userId);
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
