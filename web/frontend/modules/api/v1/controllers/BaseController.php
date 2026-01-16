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

    /**
     * Verificar autenticación: Bearer token o sesión web
     * Retorna array con 'authenticated' (bool) y 'userId' (int|null)
     * 
     * @return array ['authenticated' => bool, 'userId' => int|null]
     */
    protected function verificarAutenticacion()
    {
        $isAuthenticated = false;
        $userId = null;
        
        // Intentar autenticación por Bearer token primero
        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
        if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
                $userId = $decoded->user_id;
                $idPersona = $decoded->id_persona ?? null; // Obtener id_persona del token
                
                // Asignar idPersona a la sesión si está en el token
                if ($idPersona) {
                    $session = Yii::$app->session;
                    if (!$session->isActive) {
                        $session->open();
                    }
                    $session->set('idPersona', $idPersona);
                }
                
                $isAuthenticated = true;
            } catch (\Exception $e) {
                // Token inválido, continuar con verificación de sesión
            }
        }
        
        // Si no hay Bearer token, verificar sesión web de frontend
        if (!$isAuthenticated) {
            $session = Yii::$app->session;
            
            // Asegurar que la sesión esté iniciada
            if (!$session->isActive) {
                $session->open();
            }
            
            // Verificar si hay identidad de usuario en la sesión
            $identityId = $session->get('__id');
            $identity = $session->get('__identity');
            
            if ($identity !== null || !empty($identityId)) {
                if (is_object($identity) && method_exists($identity, 'getId')) {
                    $userId = $identity->getId();
                } elseif (is_object($identity) && isset($identity->id)) {
                    $userId = $identity->id;
                } elseif (!empty($identityId)) {
                    $userId = $identityId;
                }
                
                if (!empty($userId)) {
                    $isAuthenticated = true;
                }
            } elseif ($session->has('idPersona')) {
                // Si hay idPersona en sesión, el usuario está autenticado
                $isAuthenticated = true;
                // Intentar obtener userId desde la persona
                $idPersona = $session->get('idPersona');
                $persona = \common\models\Persona::findOne(['id_persona' => $idPersona]);
                if ($persona && $persona->id_user) {
                    $userId = $persona->id_user;
                }
            }
        }
        
        return [
            'authenticated' => $isAuthenticated,
            'userId' => $userId,
        ];
    }

    /**
     * Verificar autenticación y retornar error si no está autenticado
     * Útil para acciones que requieren autenticación pero están excluidas del authenticator
     * 
     * @return array|null Retorna null si está autenticado, o array de error si no lo está
     */
    protected function requerirAutenticacion()
    {
        $auth = $this->verificarAutenticacion();
        
        if (!$auth['authenticated']) {
            Yii::$app->response->statusCode = 401;
            return [
                'success' => false,
                'message' => 'Usuario no autenticado. Debe iniciar sesión o proporcionar un token válido.',
                'errors' => null,
            ];
        }
        
        return null;
    }
}
