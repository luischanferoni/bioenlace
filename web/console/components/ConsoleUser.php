<?php

namespace console\components;

use common\models\Person\Persona;
use Yii;
use yii\web\User;

/**
 * User de consola: identity + getters de contexto operativo sin depender de cookies.
 * Si hay sesión (p. ej. QA), lee de ahí; si no, resuelve idPersona por personas.id_user.
 */
class ConsoleUser extends User
{
    public $identityClass = \common\models\User::class;

    public $enableSession = false;

    public $enableAutoLogin = false;

    public function getIdPersona()
    {
        if (Yii::$app->has('session')) {
            try {
                $v = Yii::$app->session->get('idPersona');
                if ($v !== null && $v !== '') {
                    return $v;
                }
            } catch (\Throwable $e) {
                // seguir a fallback por BD
            }
        }

        $userId = (int) $this->id;
        if ($userId <= 0) {
            return null;
        }
        $persona = Persona::findOne(['id_user' => $userId]);

        return $persona ? $persona->id_persona : null;
    }

    public function getIdEfector()
    {
        return $this->sessionGet('idEfector');
    }

    public function getNombreEfector()
    {
        return $this->sessionGet('nombreEfector');
    }

    public function getApellidoUsuario()
    {
        return $this->sessionGet('apellidoUsuario');
    }

    public function getNombreUsuario()
    {
        return $this->sessionGet('nombreUsuario');
    }

    public function getEfectores()
    {
        return $this->sessionGet('efectores');
    }

    public function getServicios()
    {
        return $this->sessionGet('servicios');
    }

    public function getIdProfesionalEfectorServicio()
    {
        return $this->sessionGet('idProfesionalEfectorServicio');
    }

    public function getEncounterClass()
    {
        return $this->sessionGet('encounterClass');
    }

    /**
     * @return mixed|null
     */
    private function sessionGet(string $key)
    {
        if (!Yii::$app->has('session')) {
            return null;
        }
        try {
            return Yii::$app->session->get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
