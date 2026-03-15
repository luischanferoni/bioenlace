<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;
use frontend\modules\api\v1\components\ApiGhostAccessControl;

class BaseController extends Controller
{
    /** Desactivar CSRF para peticiones API (token/sesión no aplican). */
    public $enableCsrfValidation = false;

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
        $behaviors = parent::behaviors(); // []

        // Configurar CORS usando la configuración centralizada del módulo
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

        // Control de acceso por permisos (rutas api/v1/...)
        $behaviors['api-ghost-access'] = [
            'class' => ApiGhostAccessControl::class,
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
        $verbs = [];
        if (static::$frontendControllerClass !== null && property_exists(static::$frontendControllerClass, 'verbs')) {
            $verbs = static::$frontendControllerClass::$verbs ?? [];
        }
        return $verbs;
    }

    /**
     * Ejecuta una acción del controlador frontend y devuelve success(data).
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
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\yii\web\ServerErrorHttpException $e) {
            return $this->error($e->getMessage(), null, 500);
        } catch (\Throwable $e) {
            Yii::error(
                static::class . ' runFrontendAction(' . $actionId . '): ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'api'
            );
            $message = defined('YII_DEBUG') && YII_DEBUG
                ? 'Error en el servidor: ' . $e->getMessage()
                : 'Error en el servidor';
            return $this->error($message, null, 500);
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
}
