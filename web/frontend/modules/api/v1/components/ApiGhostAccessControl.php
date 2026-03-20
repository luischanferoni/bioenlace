<?php

namespace frontend\modules\api\v1\components;

use Yii;
use yii\base\ActionFilter;
use yii\web\Response;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\models\User;

/**
 * Control de acceso por ruta para la API (permisos webvimark).
 * Construye la ruta como api/v1/<controller>/<action> para coincidir con las rutas
 * listadas en el panel de permisos del backend.
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
