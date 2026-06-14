<?php

namespace frontend\components;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use common\models\Person\Persona;
use common\models\User;
use common\models\ProfesionalEfectorServicio;
use Firebase\JWT\JWT;
use common\components\Organization\Service\SesionOperativa\SesionOperativaService;

/**
 * Componente user para la aplicación web (sesión, cookie, login por formulario).
 */
class UserConfig extends BaseUserConfig
{
    public $enableAutoLogin = true;
    public $cookieLifetime = 2592000;
    public $loginUrl = ['/auth/login'];

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

                // BioenlaceDbManager arma permisos con contexto PES en sesión.
                $this->tryBootstrapSingleEfectorSession($persona);
            } else {
                throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
            }
        }

        if ($identity->status !== User::STATUS_ACTIVE) {
            Yii::$app->user->logout();
            throw new \yii\web\ForbiddenHttpException('Usuario inactivo');
        }

        \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($identity->id);

        // Refrescar permisos/rutas Bioenlace en sesión para la identidad actual.
        \common\components\Core\Permission\BioenlaceAccessChecker::refreshForIdentity($identity);
        \common\components\Actions\AllowedRoutesResolver::markSessionRoutesOwner((int) $identity->id);

        parent::afterLogin($identity, $cookieBased, $duration);
    }

    /**
     * Si la persona tiene un único vínculo PES+efector, fijar sesión como en SiteController (flujo comentado).
     * Así Route::getUserRoutes / __userRoutes reflejan permisos al primer request (login e impersonate).
     */
    private function tryBootstrapSingleEfectorSession(Persona $persona): void
    {
        $idRh = Yii::$app->user->getIdProfesionalEfectorServicio();
        if ($idRh !== null && $idRh !== '' && (int) $idRh > 0) {
            return;
        }

        $efectores = ProfesionalEfectorServicio::getEfectoresParaSesion((int) $persona->id_persona);
        if (count($efectores) !== 1) {
            return;
        }

        $row = $efectores[0];
        Yii::$app->user->setIdEfector($row['id_efector']);
        Yii::$app->user->setNombreEfector($row['nombre']);
        $idPesRow = (int) ($row['id_profesional_efector_servicio'] ?? 0);
        if ($idPesRow > 0) {
            Yii::$app->user->setIdProfesionalEfectorServicio($idPesRow);
        }

        $pesEnEfector = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => $persona->id_persona,
                'id_efector' => (int) $row['id_efector'],
                'deleted_at' => null,
            ])
            ->all();
        Yii::$app->user->setServicios(ArrayHelper::map(
            $pesEnEfector,
            'id_servicio',
            static function ($p) {
                return $p->servicio !== null ? (string) $p->servicio->nombre : '';
            }
        ));
        Yii::$app->user->setEfectores(ArrayHelper::map($efectores, 'id_efector', 'nombre'));

        SesionOperativaService::aplicarAgendaDisponibleDesdeContextoUsuario();
    }
}
