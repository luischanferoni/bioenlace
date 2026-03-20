<?php

namespace frontend\modules\api\v1\components;

use Yii;
use yii\base\ActionFilter;
use yii\web\Response;
use common\components\Actions\ActionDiscoveryService;
use common\components\Actions\ActionMappingService;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\models\User;

/**
 * Control de acceso por ruta para la API (permisos webvimark).
 * Construye la ruta como api/v1/<controller>/<action> para coincidir con las rutas
 * listadas en el panel de permisos del backend.
 *
 * Para crud/execute-action, si viene action_id se acepta también el permiso sobre la
 * ruta frontend descubierta (p. ej. /frontend/turnos/crear-mi-turno), alineado con ActionMappingService.
 */
class ApiGhostAccessControl extends ActionFilter
{
    /**
     * Acciones que no requieren comprobación de permiso (p. ej. options).
     * Los controladores pueden sobrescribir en $accessControlExcept.
     */
    public $except = ['options'];

    public function beforeAction($action)
    {
        if (in_array($action->id, $this->except, true)) {
            return true;
        }

        // uniqueId viene como "v1/turnos/mis-turnos" → en DB las rutas son "/api/turnos/mis-turnos"
        $uniqueId = $action->uniqueId;
        $parts = explode('/', $uniqueId);
        if (!empty($parts) && $parts[0] === 'v1') {
            array_shift($parts);
        }
        $routePath = implode('/', $parts);
        $route = '/api/' . $routePath;

        if (Route::isFreeAccess($route, $action)) {
            return true;
        }

        if (Yii::$app->user->isGuest) {
            $this->denyAccessJson();
            return false;
        }

        if (Yii::$app->user->identity === null) {
            if (Yii::$app->session->isActive) {
                Yii::$app->session->destroy();
            }
            $this->denyAccessJson();
            return false;
        }

        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        if (Yii::$app->user->identity->status != User::STATUS_ACTIVE) {
            Yii::$app->user->logout();
            $this->denyAccessJson('Usuario inactivo.');
            return false;
        }

        if ($route === '/api/crud/execute-action') {
            $actionId = Yii::$app->request->get('action_id');
            if ($actionId === null || $actionId === '') {
                $actionId = Yii::$app->request->post('action_id');
            }
            $actionId = is_string($actionId) ? trim($actionId) : '';
            $actionIdNorm = strtolower($actionId);
            if ($actionIdNorm !== '') {
                foreach (ActionDiscoveryService::discoverAllActions(true) as $discovered) {
                    $discId = strtolower((string) ($discovered['action_id'] ?? ''));
                    if ($discId !== $actionIdNorm) {
                        continue;
                    }
                    $targetRoute = $discovered['route'] ?? '';
                    if ($targetRoute === '') {
                        break;
                    }
                    if (Route::isFreeAccess($targetRoute, $action)) {
                        return true;
                    }
                    if (ActionMappingService::userCanAccessFrontendRoute((int) Yii::$app->user->id, $targetRoute)) {
                        return true;
                    }
                    $this->denyAccessJson();
                    return false;
                }
            }
        }

        if (User::canRoute($route)) {
            return true;
        }

        $this->denyAccessJson();
        return false;
    }

    /**
     * Responde 403 en JSON y termina la ejecución.
     */
    protected function denyAccessJson($message = 'No tiene permiso para acceder a este recurso.')
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 403;
        Yii::$app->response->data = [
            'success' => false,
            'message' => $message,
            'errors' => null,
        ];
        Yii::$app->response->send();
        Yii::$app->end();
    }
}
