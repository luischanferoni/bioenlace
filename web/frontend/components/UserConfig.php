<?php

namespace frontend\components;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Persona;
use Firebase\JWT\JWT;

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
            } else {
                throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
            }
        }

        if ($identity->status !== \webvimark\modules\UserManagement\models\User::STATUS_ACTIVE) {
            Yii::$app->user->logout();
            throw new \yii\web\ForbiddenHttpException('Usuario inactivo');
        }

        \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($identity->id);

        parent::afterLogin($identity, $cookieBased, $duration);
    }
}
