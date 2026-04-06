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

        // uniqueId → ruta DB "/api/…" (ej. turnos/ver-turno, turnos/cancelar-como-paciente, turnos/consultar-ocupacion-dia, agenda/dia)
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

        // Diagnóstico: cuando el móvil reporta 403, necesitamos ver qué ruta se chequea
        // y qué identidad/roles quedaron cargados en esta request.
        try {
            $userId = Yii::$app->user->identity ? (int) Yii::$app->user->identity->id : null;
            $roles = [];
            if ($userId) {
                $roles = array_keys(Yii::$app->authManager->getRolesByUser($userId));
            }
            $allowedRoutes = Yii::$app->session->get(\webvimark\modules\UserManagement\components\AuthHelper::SESSION_PREFIX_ROUTES, []);
            Yii::info(
                'ApiGhostAccessControl: deny 403 route=' . $route
                . ' userId=' . ($userId ?? 'null')
                . ' roles=' . json_encode($roles)
                . ' allowedRoutesCount=' . (is_array($allowedRoutes) ? count($allowedRoutes) : 0),
                'access-control'
            );
        } catch (\Throwable $e) {
            Yii::debug('ApiGhostAccessControl: debug log failed: ' . $e->getMessage(), 'access-control');
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
