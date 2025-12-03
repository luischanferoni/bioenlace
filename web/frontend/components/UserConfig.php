<?php

namespace frontend\components;

use Yii;

use yii\web\User;
use yii\web\NotFoundHttpException;

use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Route;

use common\models\Persona;

/**
 * Class UserConfig
 * @package webvimark\modules\UserManagement\components
 */
class UserConfig extends User
{
	/**
	 * @inheritdoc
	 */
	public $identityClass = 'webvimark\modules\UserManagement\models\User';

	/**
	 * @inheritdoc
	 */
	public $enableAutoLogin = true;

	/**
	 * @inheritdoc
	 */
	public $cookieLifetime = 2592000;

	/**
	 * @inheritdoc
	 */

	public $loginUrl = ['/user-management/auth/login'];

	public $idEfector;
	public $nombreEfector;
	public $idRecursoHumano;

	/**
	 * Allows to call Yii::$app->user->isSuperadmin
	 *
	 * @return bool
	 */
	public function getIsSuperadmin()
	{
		return @Yii::$app->user->identity->superadmin == 1;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return @Yii::$app->user->identity->username;
	}

	public function getIdPersona()
	{
		$session = Yii::$app->session;
	  	$idPersona = $session->get('idPersona');
	  	return $idPersona;
	}

	public function setIdRecursoHumano($idRecursoHumano)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('idRecursoHumano', $idRecursoHumano);
	}

	public function getIdRecursoHumano()
	{
		$session = Yii::$app->session;
		return $session->get('idRecursoHumano');
	}

	public function setIdEfector($idEfector)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('idEfector', $idEfector);
	}

	public function getIdEfector()
	{
		$session = Yii::$app->session;
		return $session->get('idEfector');
	}

	public function setNombreEfector($nombreEfector)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('nombreEfector', $nombreEfector);
	}

	public function getNombreEfector()
	{
		$session = Yii::$app->session;
		return $session->get('nombreEfector');
	}

	public function getApellidoUsuario()
	{
		$session = Yii::$app->session;

		$apellidoUsuario = $session->get('apellidoUsuario');

		return $apellidoUsuario;
	}

	public function getNombreUsuario()
	{
		$session = Yii::$app->session;

		$nombreUsuario = $session->get('nombreUsuario');

		return $nombreUsuario;
	}

	/*
	* Todos los efectores al que el usuario tiene acceso
	* [id_efector => nombre]
	 */
	public function setEfectores($efectores)
	{
		$session = Yii::$app->session;

		$session->set('efectores', $efectores);
	}

	public function getEfectores()
	{
		$session = Yii::$app->session;

		$efectores = $session->get('efectores');

		return $efectores;
	}

	/*
	* Todos los servicios del efector en los que el usuario trabaja
	* [id_servicio => nombre]
	 */
	public function setServicios($servicios)
	{
		$session = Yii::$app->session;

		$session->set('servicios', $servicios);
	}

	public function getServicios()
	{
		$session = Yii::$app->session;

		$servicios = $session->get('servicios');

		return $servicios;
	}

	/*
	* El servicio para el cual el rrhh esta trabajando en el momento
	* Pensado para usar en guardia e internacion
	* id_servicio
	 */
	public function setServicioActual($servicio)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('servicio_actual', $servicio);
	}

	public function getServicioActual()
	{
		$session = Yii::$app->session;
		return $session->get('servicio_actual');
	}

	/*
	* El id de rhh_servicio, el que asocia el rrhh con el servicio
	 */
	public function setIdRrhhServicio($idRrhhServicio)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('id_rrhh_servicio', $idRrhhServicio);
	}

	public function getIdRrhhServicio()
	{
		$session = Yii::$app->session;
		return $session->get('id_rrhh_servicio');
	}

	/**
	 * $servicios es un array que nos va a permitir saber de que servicio
	 * esta actualmente trabajando el rrhh (pensado para turnos, donde tenemos agenda definida)
	 */
	public function setServicioYhorarioDeTurno($servicios)
	{
		$session = Yii::$app->session;

		$session->set('servicioYhorarioDeTurno', $servicios);
	}

	public function getServicioYhorarioDeTurno()
	{
		$session = Yii::$app->session;
		$servicios = $session->get('servicioYhorarioDeTurno');

		return $servicios == null || $servicios == "" ? [] : $servicios;
	}

	public function setEncounterClass($encounterClass)
	{
		$session = Yii::$app->session;

		// Valor mutable (puede cambiar desde otra pestaña) - se guarda en session
		$session->set('encounterClass', $encounterClass);
	}

	public function getEncounterClass()
	{
		$session = Yii::$app->session;
		return $session->get('encounterClass');
	}

	/**
	 * Devuelve las sesiones que deben considerarse por-pestaña (snapshot inicial)
	 * @return array [clave => valor]
	 */
	public function getPerTabSessions()
	{
		$session = Yii::$app->session;
		return [
			'idRecursoHumano' => $session->get('idRecursoHumano'),
			'idEfector' => $session->get('idEfector'),
			'nombreEfector' => $session->get('nombreEfector'),
			'servicio_actual' => $session->get('servicio_actual'),
			'id_rrhh_servicio' => $session->get('id_rrhh_servicio'),
			'encounterClass' => $session->get('encounterClass'),
		];
	}

	/**
	 * @inheritdoc
	 */
	protected function afterLogin($identity, $cookieBased, $duration)
	{		
		if ($identity->superadmin !== 1) {
			$session = Yii::$app->session;

			$persona = Persona::findOne(['id_user' => $identity->id]);
			
			if ($persona) {
				$session->set('idPersona', $persona->id_persona);
				$session->set('apellidoUsuario', $persona->apellido);
				$session->set('nombreUsuario', $persona->nombre);
			} else {
				throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
			}
		}

		parent::afterLogin($identity, $cookieBased, $duration);
	}
}
