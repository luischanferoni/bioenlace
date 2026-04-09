<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;
use frontend\modules\api\v1\components\ApiGhostAccessControl;

class BaseController extends Controller
{
    /** Desactivar CSRF para peticiones API (token/sesión no aplican). */
    public $enableCsrfValidation = false;

    /**
     * Acciones que no requieren autenticación (solo para controladores API que no mapean a frontend, ej. AuthController).
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

        // Except del authenticator: base + $authenticatorExcept del hijo
        $except = ['options'];
        $except = array_merge($except, static::$authenticatorExcept);
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
     * Toda la API v1 responde JSON; evita repetir {@see Response::FORMAT_JSON} en cada acción.
     * El {@see ContentNegotiator} sigue disponible para Accept; esto fija el formato por defecto.
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }

    /**
     * true cuando la URL es `/api/<versión>/ui/...` (descriptor + submit de pantalla UI JSON).
     */
    protected function isApiUiRequest(): bool
    {
        $path = Yii::$app->request->getPathInfo();

        return is_string($path) && strpos($path, '/ui/') !== false;
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

}
