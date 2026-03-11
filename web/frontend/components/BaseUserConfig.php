<?php

namespace frontend\components;

use Yii;
use yii\web\User;

/**
 * Base del componente user para web y API.
 * Comparte identityClass (webvimark User) y helpers de sesión.
 */
abstract class BaseUserConfig extends User
{
    public $identityClass = 'webvimark\modules\UserManagement\models\User';

    public function getIsSuperadmin()
    {
        return @Yii::$app->user->identity->superadmin == 1;
    }

    public function getUsername()
    {
        return @Yii::$app->user->identity->username;
    }

    public function getIdPersona()
    {
        $session = Yii::$app->session;
        return $session->get('idPersona');
    }

    public function setIdRecursoHumano($idRecursoHumano)
    {
        Yii::$app->session->set('idRecursoHumano', $idRecursoHumano);
    }

    public function getIdRecursoHumano()
    {
        return Yii::$app->session->get('idRecursoHumano');
    }

    public function setIdEfector($idEfector)
    {
        Yii::$app->session->set('idEfector', $idEfector);
    }

    public function getIdEfector()
    {
        return Yii::$app->session->get('idEfector');
    }

    public function setNombreEfector($nombreEfector)
    {
        Yii::$app->session->set('nombreEfector', $nombreEfector);
    }

    public function getNombreEfector()
    {
        return Yii::$app->session->get('nombreEfector');
    }

    public function getApellidoUsuario()
    {
        return Yii::$app->session->get('apellidoUsuario');
    }

    public function getNombreUsuario()
    {
        return Yii::$app->session->get('nombreUsuario');
    }

    public function setEfectores($efectores)
    {
        Yii::$app->session->set('efectores', $efectores);
    }

    public function getEfectores()
    {
        return Yii::$app->session->get('efectores');
    }

    public function setServicios($servicios)
    {
        Yii::$app->session->set('servicios', $servicios);
    }

    public function getServicios()
    {
        return Yii::$app->session->get('servicios');
    }

    public function setServicioActual($servicio)
    {
        Yii::$app->session->set('servicio_actual', $servicio);
    }

    public function getServicioActual()
    {
        return Yii::$app->session->get('servicio_actual');
    }

    public function setIdRrhhServicio($idRrhhServicio)
    {
        Yii::$app->session->set('id_rrhh_servicio', $idRrhhServicio);
    }

    public function getIdRrhhServicio()
    {
        return Yii::$app->session->get('id_rrhh_servicio');
    }

    public function setServicioYhorarioDeTurno($servicios)
    {
        Yii::$app->session->set('servicioYhorarioDeTurno', $servicios);
    }

    public function getServicioYhorarioDeTurno()
    {
        $servicios = Yii::$app->session->get('servicioYhorarioDeTurno');
        return $servicios == null || $servicios === '' ? [] : $servicios;
    }

    public function setEncounterClass($encounterClass)
    {
        Yii::$app->session->set('encounterClass', $encounterClass);
    }

    public function getEncounterClass()
    {
        return Yii::$app->session->get('encounterClass');
    }

    /**
     * Sesiones por-pestaña (snapshot inicial).
     * @return array [clave => valor]
     */
    public function getPerTabSessions()
    {
        $s = Yii::$app->session;
        return [
            'idRecursoHumano' => $s->get('idRecursoHumano'),
            'idEfector' => $s->get('idEfector'),
            'nombreEfector' => $s->get('nombreEfector'),
            'servicio_actual' => $s->get('servicio_actual'),
            'id_rrhh_servicio' => $s->get('id_rrhh_servicio'),
            'encounterClass' => $s->get('encounterClass'),
        ];
    }
}
