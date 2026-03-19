<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\helpers\Inflector;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use common\components\Actions\ActionMappingService;

/**
 * Endpoints dedicados a **descriptores de UI** (JSON dinámico: wizards, etc.).
 *
 * Convención: GET /api/v1/ui/{entidad}/{accion}
 * Ejemplo: GET /api/v1/ui/turnos/crear-mi-turno
 *
 * El permiso se alinea con las acciones descubiertas (mismo action_id que execute-action: entidad.accion).
 */
class UiController extends BaseController
{
    /**
     * CORS preflight.
     * @return array
     */
    public function actionOptions()
    {
        return [];
    }

    /**
     * Devuelve el descriptor de UI para la entidad/acción (típicamente GET al controlador frontend homólogo).
     *
     * @param string $entity ej. turnos
     * @param string $action ej. crear-mi-turno
     * @return array
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionDescriptor($entity, $action)
    {
        $entity = strtolower(trim((string) $entity));
        $action = strtolower(trim((string) $action));
        if ($entity === '' || $action === '') {
            throw new BadRequestHttpException('Los parámetros entity y action son obligatorios.');
        }

        $userId = Yii::$app->user->id;
        if (!$userId) {
            throw new ForbiddenHttpException('Debe estar autenticado.');
        }

        $actionId = $entity . '.' . $action;
        $mapped = $this->findAvailableActionById($actionId, $userId);
        if ($mapped === null) {
            throw new ForbiddenHttpException('No tiene permiso para esta definición de UI o la acción no existe.');
        }

        $params = Yii::$app->request->get();
        unset($params['entity'], $params['action'], $params['r']);

        return $this->invokeFrontendGetAndReturnArray($mapped, $params);
    }

    /**
     * @param string $actionId
     * @param int $userId
     * @return array|null
     */
    private function findAvailableActionById($actionId, $userId)
    {
        foreach (ActionMappingService::getAvailableActionsForUser($userId) as $a) {
            if (($a['action_id'] ?? '') === $actionId) {
                return $a;
            }
        }
        return null;
    }

    /**
     * Invoca action{Accion} del controlador frontend en contexto GET (misma estrategia que CrudController).
     *
     * @param array $action registro de ActionDiscovery (controller, action, action_id, …)
     * @param array $params query params adicionales
     * @return array
     */
    private function invokeFrontendGetAndReturnArray(array $action, array $params)
    {
        $controllerClass = 'frontend\\controllers\\' . ucfirst($action['controller']) . 'Controller';
        $actionName = $action['action'];
        $methodName = 'action' . Inflector::id2camel($actionName, '-');

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $methodName)) {
            throw new NotFoundHttpException('Descriptor de UI no encontrado: ' . $action['controller'] . '/' . $actionName);
        }

        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new ForbiddenHttpException('Debe estar autenticado.');
        }

        $originalUserIdentity = Yii::$app->user->identity;
        Yii::$app->user->setIdentity($user);
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

        try {
            $controller = new $controllerClass($action['controller'], Yii::$app);
            $controller->enableCsrfValidation = false;

            $originalBehaviors = $controller->behaviors();
            $controller->detachBehaviors();
            foreach ($originalBehaviors as $name => $behavior) {
                if ($name !== 'ghost-access') {
                    $controller->attachBehavior($name, $behavior);
                }
            }

            $originalGet = $_GET;
            $originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'POST';
            $actionId = $action['action_id'] ?? null;
            $_GET = array_merge(['action_id' => $actionId], $params);
            $_SERVER['REQUEST_METHOD'] = 'GET';
            Yii::$app->request->setQueryParams($_GET);

            $reflectionRequest = new \ReflectionClass(Yii::$app->request);
            if ($reflectionRequest->hasProperty('_method')) {
                $methodProperty = $reflectionRequest->getProperty('_method');
                $methodProperty->setAccessible(true);
                $methodProperty->setValue(Yii::$app->request, 'GET');
            }

            try {
                $originalFormat = Yii::$app->response->format;
                $originalData = Yii::$app->response->data;
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

                ob_start();
                try {
                    $reflection = new \ReflectionMethod($controller, $methodName);
                    $reflection->setAccessible(true);
                    $result = $reflection->invoke($controller);
                } catch (\Throwable $e) {
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if ($e instanceof \yii\web\HttpException) {
                        throw $e;
                    }
                    Yii::error('UiController invoke: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-ui');
                    throw new ServerErrorHttpException('Error al obtener la definición de UI.', 0, $e);
                }
                $output = ob_get_clean();

                if (!empty($output) && $result === null) {
                    Yii::warning(
                        "UiController: el método {$methodName} generó salida pero no retornó valor. Output: " . substr($output, 0, 200),
                        'api-ui'
                    );
                }

                if ($result === null) {
                    Yii::error("UiController: {$methodName} devolvió null.", 'api-ui');
                    throw new ServerErrorHttpException('No se pudo obtener la definición de UI.');
                }
                if (!is_array($result)) {
                    Yii::error('UiController: se esperaba array, se obtuvo ' . gettype($result), 'api-ui');
                    throw new ServerErrorHttpException('La definición de UI tiene un formato inválido.');
                }

                return $result;
            } finally {
                Yii::$app->response->format = $originalFormat;
                Yii::$app->response->data = $originalData;
                $_GET = $originalGet;
                $_SERVER['REQUEST_METHOD'] = $originalMethod;
            }
        } finally {
            Yii::$app->user->setIdentity($originalUserIdentity);
        }
    }

    public function actions()
    {
        return [];
    }
}
