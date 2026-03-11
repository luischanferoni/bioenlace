<?php

namespace frontend\components;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Componente user para la API (JWT; sesión solo para datos derivados como idPersona).
 */
class ApiUser extends BaseUserConfig
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
                $rrhhEfectores = RrhhEfector::getEfectores($persona->id_persona);
                $session->set('efectores', $rrhhEfectores);
            } else {
                throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
            }
        }

        parent::afterLogin($identity, $cookieBased, $duration);
    }
}
