<?php

namespace frontend\components;

use Yii;

use yii\web\User;
use yii\web\NotFoundHttpException;

use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Route;

use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Class ApiUser
 * la clase user (Yii::$app->user) pero para las apis, cuando se logean via rest
 * @package webvimark\modules\UserManagement\components
 */
class ApiUser extends User
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

	public function setIdRecursoHumano($idRecursoHumano)
	{
		$session = Yii::$app->session;
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
		$session->set('nombreEfector', $nombreEfector);
	}

	public function getNombreEfector()
	{
		$session = Yii::$app->session;
		return $session->get('nombreEfector');
	}

	public function setServicios($servicios)
	{
		$session = Yii::$app->session;
		$session->set('servicios', $servicios);
	}

	public function getServicios()
	{
		$session = Yii::$app->session;
		return $session->get('servicios');
	}

	public function setServicioActual($servicio)
	{
		$session = Yii::$app->session;
		$session->set('servicio_actual', $servicio);
	}

	public function getServicioActual()
	{
		$session = Yii::$app->session;
		return $session->get('servicio_actual');
	}

	public function setIdRrhhServicio($idRrhhServicio)
	{
		$session = Yii::$app->session;
		$session->set('id_rrhh_servicio', $idRrhhServicio);
	}

	public function getIdRrhhServicio()
	{
		$session = Yii::$app->session;
		return $session->get('id_rrhh_servicio');
	}

	public function setEncounterClass($encounterClass)
	{
		$session = Yii::$app->session;
		$session->set('encounterClass', $encounterClass);
	}

	public function getEncounterClass()
	{
		$session = Yii::$app->session;
		return $session->get('encounterClass');
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

				$rrhhEfectores = RrhhEfector::getEfectores($persona->id_persona);
				$session->set('efectores', $rrhhEfectores);

			} else {
				throw new NotFoundHttpException('Hubo un error con su usuario, comuníquese con los encargados del sistema.');
			}
		}

		parent::afterLogin($identity, $cookieBased, $duration);
	}
}
