<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Platform\Core\Db\BioenlaceDb;
use yii\web\Controller;
use yii\filters\Cors;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\components\Platform\Core\Http\Contract\ApiResponder;
use frontend\modules\api\v1\components\JsonHttpBearerAuth;
use frontend\modules\api\v1\components\BioenlaceApiAccessControl;

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
            'class' => BioenlaceApiAccessControl::class,
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
        BioenlaceDb::ensureConnection();

        return parent::beforeAction($action);
    }

    /**
     * Respuesta estándar para preflight CORS (OPTIONS).
     *
     * Se usa por reglas de URL genéricas para evitar declarar OPTIONS por endpoint.
     *
     * @return array<string, mixed>
     */
    public function actionOptions(): array
    {
        Yii::$app->response->statusCode = 204;
        return [];
    }

    /**
     * Convierte {@see ApiException} en la respuesta de error del contrato.
     */
    public function runAction($id, $params = [])
    {
        try {
            return parent::runAction($id, $params);
        } catch (ApiException $e) {
            return $this->fail($e->errorCode(), $e->params(), $e->fieldErrors(), $e->meta());
        }
    }

    /**
     * Adapta el resultado de un caso de uso al contrato: la clave `message` (aviso del backend)
     * pasa a `meta.message`; el resto es `data`.
     *
     * @param array<string, mixed> $result
     */
    protected function okFromUseCase(array $result): array
    {
        $meta = [];
        if (isset($result['message']) && is_string($result['message']) && $result['message'] !== '') {
            $meta['message'] = $result['message'];
        }
        unset($result['message']);

        return $this->ok($result, $meta);
    }

    /**
     * Éxito del contrato: {data, meta}.
     *
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    protected function ok($data = null, array $meta = [], int $status = 200): array
    {
        Yii::$app->response->statusCode = $status;
        return ApiResponder::success($data, $meta);
    }

    /**
     * Error del contrato: {error: {code, message, intent, retryable, field_errors?}}.
     *
     * @param array<string, scalar> $params
     * @param array<string, list<string>> $fieldErrors
     * @param array<string, mixed> $meta
     */
    protected function fail(string $code, array $params = [], array $fieldErrors = [], array $meta = []): array
    {
        $result = ApiResponder::error($code, $params, $fieldErrors, $meta);
        Yii::$app->response->statusCode = $result['status'];
        return $result['body'];
    }

    /**
     * Error de validación con `field_errors` tomados del modelo.
     */
    protected function failValidation(\yii\base\Model $model): array
    {
        return $this->fail('validation_failed', [], $model->getErrors());
    }

    /**
     * @deprecated Formato anterior; se elimina al migrar todos los controllers (fase 9). Usar {@see ok()}.
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
     * @deprecated Formato anterior; usar {@see fail()}.
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
