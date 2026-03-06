<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;

class BaseController extends ActiveController
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];

    /**
     * Clase del controlador frontend que este controlador API mapea.
     * Si est? definida, except y verbs se leen de ese controlador (fuente ?nica Web + API).
     */
    public static $frontendControllerClass = null;

    /**
     * Acciones que no requieren autenticaci?n (solo para controladores API que no mapean a frontend, ej. AuthController).
     * Los controladores mapeadores usan $frontendControllerClass y toman except del controlador frontend.
     */
    public static $authenticatorExcept = [];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Configurar CORS usando la configuraci?n centralizada del m?dulo
        $allowedOrigins = \frontend\modules\api\v1\Module::getAllowedOrigins();
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

        // Except del authenticator: base + (si hay frontend, desde ah?; si no, desde $authenticatorExcept del hijo)
        $except = ['options'];
        if (static::$frontendControllerClass !== null && property_exists(static::$frontendControllerClass, 'authenticatorExcept')) {
            $except = array_merge($except, static::$frontendControllerClass::$authenticatorExcept ?? []);
        } else {
            $except = array_merge($except, static::$authenticatorExcept);
        }
        $behaviors['authenticator'] = [
            'class' => JsonHttpBearerAuth::class,
            'except' => array_values(array_unique($except)),
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

    /**
     * Verbs: si este controlador mapea a un frontend, se fusionan los verbs definidos all?.
     */
    protected function verbs()
    {
        $verbs = parent::verbs();
        if (static::$frontendControllerClass !== null && property_exists(static::$frontendControllerClass, 'verbs')) {
            $frontendVerbs = static::$frontendControllerClass::$verbs ?? [];
            $verbs = array_merge($verbs, $frontendVerbs);
        }
        return $verbs;
    }

    public function actions()
    {
        $actions = parent::actions();
        
        // Deshabilitar acciones por defecto que no necesitamos
        unset($actions['delete'], $actions['create'], $actions['update']);
        
        return $actions;
    }

    /**
     * Ejecuta una acci?n del controlador frontend y devuelve success(data).
     * Para controladores API que solo mapean: usa $frontendControllerClass y convierte excepciones en error JSON.
     */
    protected function runFrontendAction($actionId, $params = [])
    {
        $className = static::$frontendControllerClass;
        if ($className === null) {
            throw new \yii\web\ServerErrorHttpException('frontendControllerClass no definido');
        }
        $id = strtolower(preg_replace('/Controller$/', '', (new \ReflectionClass($className))->getShortName()));
        try {
            $controller = new $className($id, Yii::$app);
            $result = $controller->runAction($actionId, $params);
            if (isset($result['success']) && isset($result['data'])) {
                return $result;
            }
            return $this->success($result);
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), null, 404);
        } catch (BadRequestHttpException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\yii\web\ServerErrorHttpException $e) {
            return $this->error($e->getMessage(), null, 500);
        } catch (\Throwable $e) {
            Yii::warning(static::class . ' runFrontendAction(' . $actionId . '): ' . $e->getMessage(), 'api');
            return $this->error('Error en el servidor', null, 500);
        }
    }

    /**
     * Respuesta de ?xito est?ndar
     */
    protected function success($data = null, $message = 'Operaci?n exitosa', $code = 200)
    {
        Yii::$app->response->statusCode = $code;
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Respuesta de error est?ndar
     */
    protected function error($message = 'Error en la operaci?n', $errors = null, $code = 400)
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
            return $this->error('Datos inv?lidos', $errors, 422);
        }
        
        return true;
    }

    /**
     * Verificar autenticaci?n: Bearer token o sesi?n web
     * Retorna array con 'authenticated' (bool) y 'userId' (int|null)
     * 
     * @return array ['authenticated' => bool, 'userId' => int|null]
     */
    protected function verificarAutenticacion()
    {
        $isAuthenticated = false;
        $userId = null;
        
        // Intentar autenticaci?n por Bearer token primero
        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
        if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
                $userId = $decoded->user_id;
                $idPersona = $decoded->id_persona ?? null; // Obtener id_persona del token
                
                // Asignar idPersona a la sesi?n si est? en el token
                if ($idPersona) {
                    $session = Yii::$app->session;
                    if (!$session->isActive) {
                        $session->open();
                    }
                    $session->set('idPersona', $idPersona);
                }
                
                $isAuthenticated = true;
            } catch (\Exception $e) {
                // Token inv?lido, continuar con verificaci?n de sesi?n
            }
        }
        
        // Si no hay Bearer token, verificar sesi?n web de frontend
        if (!$isAuthenticated) {
            $session = Yii::$app->session;
            
            // Asegurar que la sesi?n est? iniciada
            if (!$session->isActive) {
                $session->open();
            }
            
            // Verificar si hay identidad de usuario en la sesi?n
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
                // Si hay idPersona en sesi?n, el usuario est? autenticado
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
     * Verificar autenticaci?n y retornar error si no est? autenticado
     * ?til para acciones que requieren autenticaci?n pero est?n excluidas del authenticator
     * 
     * @return array|null Retorna null si est? autenticado, o array de error si no lo est?
     */
    protected function requerirAutenticacion()
    {
        $auth = $this->verificarAutenticacion();
        
        if (!$auth['authenticated']) {
            Yii::$app->response->statusCode = 401;
            return [
                'success' => false,
                'message' => 'Usuario no autenticado. Debe iniciar sesi?n o proporcionar un token v?lido.',
                'errors' => null,
            ];
        }
        
        return null;
    }
}
