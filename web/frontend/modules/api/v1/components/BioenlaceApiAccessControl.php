<?php

namespace frontend\modules\api\v1\components;

use common\components\Platform\Core\Permission\ApiRoutePermissionResolver;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\BioenlaceSessionPermissions;
use common\components\Platform\Core\Permission\FlowStepAccessService;
use Yii;
use yii\base\ActionFilter;
use yii\web\Response;

/**
 * Control de acceso API v1 sobre Yii RBAC ({@see BioenlaceDbManager}), sin webvimark.
 */
class BioenlaceApiAccessControl extends ActionFilter
{
    /** @var list<string> */
    protected static array $authenticatedOnlyRoutes = [
        '/api/sesion-operativa/establecer',
        '/api/acciones/comunes',
        '/api/asistente/enviar',
        '/api/asistente/estado',
        '/api/chat/recibir',
        '/api/chat/estado',
        '/api/client-diagnostic/registrar',
        '/api/audio/stt-config',
        '/api/media/ver',
        '/api/consulta-chat/listar-mensajes',
        '/api/consulta-chat/enviar',
        '/api/consulta-chat/subir',
        '/api/consulta-chat/estado',
        '/api/notificaciones/listar',
        '/api/notificaciones/marcar-leida',
        '/api/notificaciones/registrar-interaccion-push-propia',
        '/api/device/push-token',
        '/api/devices/push-token',
        '/api/paciente-contexto/obtener-como-paciente',
        '/api/paciente-contexto/actualizar-como-paciente',
        '/api/paciente-contexto/sugerir-provincias-como-paciente',
        '/api/paciente-contexto/buscar-recurso-provincial-como-paciente',
        '/api/home/panel',
        '/api/person-representation/solicitar-menor-como-tutor',
        '/api/person-representation/mis-vinculos-como-tutor',
        '/api/person-representation/designar-representante',
        '/api/person-representation/revocar-representante',
        '/api/person-representation/mis-representantes',
        '/api/person-representation/preferencias-como-paciente',
        '/api/person-representation/pacientes-a-cargo',
        '/api/person-representation/establecer-sujeto-paciente',
    ];

    /** @var list<string> */
    public $except = ['options'];

    public function beforeAction($action): bool
    {
        if (in_array($action->id, $this->except, true)) {
            return true;
        }

        $routesToCheck = ApiRoutePermissionResolver::checkedRoutesForAction(
            (string) Yii::$app->request->pathInfo,
            (string) $action->uniqueId
        );

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

        $identity = Yii::$app->user->identity;
        if (!BioenlaceAccessChecker::isActiveIdentity($identity)) {
            Yii::$app->user->logout();
            $this->denyAccessJson('Usuario inactivo.');
            return false;
        }

        BioenlaceAccessChecker::ensureUpToDate();

        if (BioenlaceAccessChecker::isSuperadminUserId((int) $identity->getId())) {
            return true;
        }

        foreach ($routesToCheck as $route) {
            $normalized = BioenlaceSessionPermissions::unifyRoute($route);
            if (in_array($normalized, self::$authenticatedOnlyRoutes, true)) {
                return true;
            }
        }

        $userId = (int) $identity->getId();
        foreach ($routesToCheck as $route) {
            if (BioenlaceAccessChecker::userCanApiRoute($userId, $route)) {
                return true;
            }
        }

        $flowIntentId = Yii::$app->request->getHeaders()->get(FlowStepAccessService::HEADER_FLOW_INTENT_ID);
        $flowIntentId = is_string($flowIntentId) ? trim($flowIntentId) : null;
        if ($flowIntentId === '') {
            $flowIntentId = null;
        }
        foreach ($routesToCheck as $route) {
            if ((new FlowStepAccessService())->canAccessViaParentIntent($userId, $route, $flowIntentId)) {
                return true;
            }
        }

        try {
            $roles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
            Yii::info(
                'BioenlaceApiAccessControl: deny 403 routes=' . json_encode($routesToCheck)
                . ' userId=' . $userId
                . ' roles=' . json_encode($roles),
                'access-control'
            );
        } catch (\Throwable $e) {
            Yii::debug('BioenlaceApiAccessControl: debug log failed: ' . $e->getMessage(), 'access-control');
        }

        $this->denyAccessJson();
        return false;
    }

    /**
     * @return list<string>
     */
    public static function permissionRouteCandidates(string $route): array
    {
        return ApiRoutePermissionResolver::candidates($route);
    }

    protected function denyAccessJson(string $message = 'No tiene permiso para acceder a este recurso.'): void
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 403;
        $data = [
            'success' => false,
            'message' => $message,
            'errors' => null,
        ];
        try {
            $dbg = Yii::$app->request->getHeaders()->get('X-Bioenlace-Debug');
            if ($dbg === '1' || $dbg === 1) {
                $userId = Yii::$app->user->identity ? (int) Yii::$app->user->identity->id : null;
                $roles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
                $routes = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROUTES, []);
                $data['debug'] = [
                    'route' => Yii::$app->requestedRoute,
                    'userId' => $userId,
                    'roles' => is_array($roles) ? $roles : [],
                    'allowedRoutesCount' => is_array($routes) ? count($routes) : 0,
                ];
            }
        } catch (\Throwable $e) {
            // noop
        }
        Yii::$app->response->data = $data;
        Yii::$app->response->send();
        Yii::$app->end();
    }
}
