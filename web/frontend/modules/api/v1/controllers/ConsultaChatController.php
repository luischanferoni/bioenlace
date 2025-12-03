<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\filters\Cors;
use yii\web\Response;
use common\models\ConsultaChatMessage;
use common\models\Consulta;

class ConsultaChatController extends \yii\rest\Controller
{
    public $enableCsrfValidation = false;
    protected $_verbs = ['GET', 'POST', 'OPTIONS'];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove auth filter before cors
        unset($behaviors['authenticator']);

        // add CORS filter
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => ['http://localhost:3000', 'http://localhost:52294', 'https://riesgo-dbt.msalsgo.gob.ar'],
                'Access-Control-Request-Method' => $this->_verbs,
                'Access-Control-Allow-Headers' => ['content-type', 'authorization'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 3600,
                'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
            ],
        ];

        return $behaviors;
    }

    /**
     * Send the HTTP options available to this route
     */
    public function actionOptions()
    {
        if (Yii::$app->getRequest()->getMethod() !== 'OPTIONS') {
            Yii::$app->getResponse()->setStatusCode(405);
        }
        Yii::$app->getResponse()->getHeaders()->set('Allow', implode(', ', $this->_verbs));
    }

    /**
     * Obtener mensajes de una consulta
     */
    public function actionMessages($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            // Verificar que la consulta existe
            $consulta = Consulta::findOne($consulta_id);
            if (!$consulta) {
                return [
                    'success' => false,
                    'message' => 'Consulta no encontrada',
                    'data' => null
                ];
            }

            // Obtener mensajes de la consulta
            $messages = ConsultaChatMessage::find()
                ->where(['consulta_id' => $consulta_id])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            $formattedMessages = [];
            foreach ($messages as $message) {
                $formattedMessages[] = [
                    'id' => $message->id,
                    'content' => $message->content,
                    'user_id' => $message->user_id,
                    'user_name' => $message->user_name,
                    'user_role' => $message->user_role,
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
                        'paciente' => $consulta->persona->getNombreCompleto() ?? 'Paciente',
                    ],
                ]
            ];

        } catch (\Exception $e) {
            Yii::error('Error obteniendo mensajes: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del servidor',
                'data' => null
            ];
        }
    }

    /**
     * Enviar mensaje
     */
    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $body = Yii::$app->request->getBodyParams();
            $consulta_id = $body['consulta_id'] ?? null;
            $message = $body['message'] ?? null;
            $user_id = $body['user_id'] ?? null;
            $user_role = $body['user_role'] ?? null;

            if (!$consulta_id || !$message || !$user_id) {
                return [
                    'success' => false,
                    'message' => 'Datos requeridos: consulta_id, message, user_id',
                    'data' => null
                ];
            }

            // Verificar que la consulta existe
            $consulta = Consulta::findOne($consulta_id);
            if (!$consulta) {
                return [
                    'success' => false,
                    'message' => 'Consulta no encontrada',
                    'data' => null
                ];
            }

            // Obtener información del usuario
            $user = \common\models\User::findOne($user_id);
            $userName = $user ? $user->username : 'Usuario';

            // Crear mensaje
            $chatMessage = new ConsultaChatMessage();
            $chatMessage->consulta_id = $consulta_id;
            $chatMessage->user_id = $user_id;
            $chatMessage->user_name = $userName;
            $chatMessage->user_role = $user_role;
            $chatMessage->content = $message;
            $chatMessage->message_type = 'texto';

            if (!$chatMessage->save()) {
                return [
                    'success' => false,
                    'message' => 'Error guardando mensaje: ' . implode(', ', $chatMessage->getFirstErrors()),
                    'data' => null
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
                    'created_at' => $chatMessage->created_at,
                    'is_read' => $chatMessage->is_read,
                ]
            ];

        } catch (\Exception $e) {
            Yii::error('Error enviando mensaje: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del servidor',
                'data' => null
            ];
        }
    }

    /**
     * Obtener estado del chat
     */
    public function actionStatus($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            // Verificar que la consulta existe
            $consulta = Consulta::findOne($consulta_id);
            if (!$consulta) {
                return [
                    'success' => false,
                    'message' => 'Consulta no encontrada',
                    'data' => null
                ];
            }

            // Contar mensajes no leídos
            $unreadCount = ConsultaChatMessage::find()
                ->where(['consulta_id' => $consulta_id, 'is_read' => false])
                ->count();

            return [
                'success' => true,
                'message' => 'Estado obtenido exitosamente',
                'data' => [
                    'consulta_id' => $consulta_id,
                    'unread_count' => $unreadCount,
                    'is_online' => true,
                    'last_activity' => $consulta->updated_at ?? $consulta->created_at,
                ]
            ];

        } catch (\Exception $e) {
            Yii::error('Error obteniendo estado: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del servidor',
                'data' => null
            ];
        }
    }
}
