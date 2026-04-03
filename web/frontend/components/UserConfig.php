<?php

namespace frontend\components;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use Firebase\JWT\JWT;
use frontend\controllers\SiteController;

/**
 * Componente user para la aplicación web (sesión, cookie, login por formulario).
 */
class UserConfig extends BaseUserConfig
{
    public $enableAutoLogin = true;
    public $cookieLifetime = 2592000;
    public $loginUrl = ['/user-management/auth/login'];

    protected function afterLogin($identity, $cookieBased, $duration)
    {
        if ($identity->superadmin !== 1) {
            $session = Yii::$app->session;
            $persona = Persona::findOne(['id_user' => $identity->id]);
            if ($persona) {
                $session->set('idPersona', $persona->id_persona);
                $session->set('apellidoUsuario', $persona->apellido);
                $session->set('nombreUsuario', $persona->nombre);

                // Generar token JWT para que la SPA web use la API v1 igual que el móvil
                $payload = [
                    'user_id' => $identity->id,
                    'email' => $identity->email,
                    'id_persona' => $persona->id_persona,
                    'iat' => time(),
                    'exp' => time() + (24 * 60 * 60),
                ];
                $token = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
                $session->set('apiJwtToken', $token);

                // BioenlaceDbManager arma permisos con getIdRecursoHumano(); sin esto, __userRoutes queda vacío
                // hasta elegir efector (impersonate nunca pasa por ese POST).
                $this->tryBootstrapSingleEfectorSession($persona);
            } else {
                throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
            }
        }

        if ($identity->status !== \webvimark\modules\UserManagement\models\User::STATUS_ACTIVE) {
            Yii::$app->user->logout();
            throw new \yii\web\ForbiddenHttpException('Usuario inactivo');
        }

        \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($identity->id);

        // Igual que webvimark UserConfig: refrescar __userRoles / __userRoutes para la identidad actual.
        // Imprescindible tras impersonate (SiteController::actionImpersonate): si no, la sesión conserva
        // rutas del usuario anterior y ghost-access / RBAC en sesión quedan desalineados con AllowedRoutesResolver.
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions($identity);
        \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) $identity->id);

        parent::afterLogin($identity, $cookieBased, $duration);
    }

    /**
     * Si la persona tiene un único vínculo RRHH+efector, fijar sesión como en SiteController (flujo comentado).
     * Así Route::getUserRoutes / __userRoutes reflejan permisos al primer request (login e impersonate).
     */
    private function tryBootstrapSingleEfectorSession(Persona $persona): void
    {
        $idRh = Yii::$app->user->getIdRecursoHumano();
        if ($idRh !== null && $idRh !== '' && (int) $idRh > 0) {
            return;
        }

        $efectores = RrhhEfector::getEfectores($persona->id_persona);
        if (count($efectores) !== 1) {
            return;
        }

        $row = $efectores[0];
        Yii::$app->user->setIdEfector($row['id_efector']);
        Yii::$app->user->setNombreEfector($row['nombre']);
        Yii::$app->user->setIdRecursoHumano($row['id_rr_hh']);

        $rrhhServicio = RrhhServicio::findActive()->andWhere(['id_rr_hh' => $row['id_rr_hh']])->all();
        Yii::$app->user->setServicios(ArrayHelper::map($rrhhServicio, 'id_servicio', 'servicio.nombre'));
        Yii::$app->user->setEfectores(ArrayHelper::map($efectores, 'id_efector', 'nombre'));

        SiteController::establecerAgendaDisponible($row['id_rr_hh']);
    }
}
