<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;

class BaseController extends ActiveController
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Configurar CORS usando la configuración centralizada del módulo
        $allowedOrigins = \frontend\modules\api\v1\Module::getAllowedOrigins();
        // Agregar * para mobile si es necesario (pero solo si no se requiere credentials)
        // Si se requiere credentials, no se puede usar *
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => $allowedOrigins,
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Max-Age' => 86400,
            ],
        ];

        // Configurar autenticación con componente personalizado que siempre devuelve JSON
        $behaviors['authenticator'] = [
            'class' => JsonHttpBearerAuth::class,
            'except' => ['options', 'login', 'register'],
        ];

        // Configurar content negotiator
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        
        // Deshabilitar acciones por defecto que no necesitamos
        unset($actions['delete'], $actions['create'], $actions['update']);
        
        return $actions;
    }

    /**
     * Respuesta de éxito estándar
     */
    protected function success($data = null, $message = 'Operación exitosa', $code = 200)
    {
        Yii::$app->response->statusCode = $code;
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Respuesta de error estándar
     */
    protected function error($message = 'Error en la operación', $errors = null, $code = 400)
    {
        Yii::$app->response->statusCode = $code;
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * Validar datos de entrada
     */
    protected function validateData($data, $rules)
    {
        $validator = new \yii\validators\Validator();
        $validator->attributes = array_keys($data);
        $validator->rules = $rules;
        
        if (!$validator->validate($data, $errors)) {
            return $this->error('Datos inválidos', $errors, 422);
        }
        
        return true;
    }
}
