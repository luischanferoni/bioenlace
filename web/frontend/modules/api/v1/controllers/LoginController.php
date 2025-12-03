<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use Exception;
use yii\filters\Cors;
use sizeg\jwt\Jwt;
use sizeg\jwt\JwtHttpBearerAuth;
use yii\filters\auth\HttpHeaderAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;

use common\models\Persona;
use common\models\User;
use common\models\Localidad;
use common\models\Barrios;

use common\models\LoginForm;
use common\models\RrhhEfector;

class LoginController extends \yii\rest\Controller
{
	public $enableCsrfValidation = false;
  	// Add more verbs here if needed
  	protected $_verbs = ['POST','OPTIONS'];

  	public function behaviors()
  	{
		$behaviors = parent::behaviors();

      // remove auth filter before cors if you are using it
      unset($behaviors['authenticator']);

      // add CORS filter
      $behaviors['corsFilter'] = [
          'class' => Cors::class,
          'cors' => [
			 'Origin' => ['http://localhost:3000', 'https://riesgo-dbt.msalsgo.gob.ar', 'http://riesgo-dbt.msalsgo.gob.ar'], // restrict access to
              'Access-Control-Request-Method' => $this->_verbs,
              // Not sure if you are using authorization filter
              'Access-Control-Allow-Headers' => ['content-type','authorization'], 
              // Try '*' first, once it works, make it more restrictive
              'Access-Control-Request-Headers' => ['*'],
              'Access-Control-Allow-Credentials' => true,
              'Access-Control-Max-Age' => 3600,
              'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
          ],
      ];

      // re-add authentication filter if you are using it.
      $behaviors['authenticator'] = [
          'class' => HttpBearerAuth::class,
      ];
      // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method) if using authentication filter.
      $behaviors['authenticator']['except'] = ['options', 'login'];

      return $behaviors;
  	}

  // I do not think you need this method, 
  // this should already be mapped in the rules
 /* protected function verbs()
  {
      return [
          'login' => ['POST'],
      ];
  }*/
    /**
     * Send the HTTP options available to this route
     */
    public function actionOptions()
    {
        if (Yii::$app->getRequest()->getMethod() !== 'OPTIONS') {
            Yii::$app->getResponse()->setStatusCode(405);
        }
        Yii::$app->getResponse()->getHeaders()->set('Allow', implode(', ', $this->_verbs));
    }

    public function actionLogin()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $model = new LoginForm();

        if (!Yii::$app->request->post()) {
            \Yii::error("Request sin post en el login");
            return ['success' => false, 'msg' => 'Ocurrió un error, comuníquese con el personal de Salud Digital'];
        }

        $model->username = Yii::$app->request->post()["username"];
        $model->password = Yii::$app->request->post()["password"];    

        if (!$model->login()) {
            return ['success' => false, 'msg' => 'Usuario y/o contraseña incorrectos'];
        }

       // if (!User::hasRole(['Agente'])) {
            // throw new UnauthorizedHttpException;
       // }

        //return Yii::$app->user->getIdPersona();
        
        $user = $model->getUser();
        \Yii::info("User id:" . $user->id);

        $efectores = Yii::$app->user->getEfectores();
        \Yii::info("Cant efectores: " . count($efectores));

        if ($efectores == 0) {
            return [
                'success' => false, 
                'msg' => 'No se encuentra asignado a ningun servicio en ningun efector, comuníquese con el administrador de su Efector o el personal de Salud Digital'
            ];
        }
        
        $localidades = [];

        foreach ($efectores as $j => $efector) {

            $localidades = Localidad::getLocalidadesCercanas($efector["id_localidad"]);

            foreach ($localidades as $i => $localidad) {
                unset($localidades[$i]['dist']);
                $localidades[$i]['barrios'] = Barrios::barriosPorLocalidad($localidad["id_localidad"]);
            }
            $efectores[$j]['localidades'] = $localidades;
        }
        
        return  [ 
                "success" => true,
                "token" => $user->getAuthKey(),                    
                "user" => [
                        "id" => $user->id,
                        "nombre" => Yii::$app->user->getNombreUsuario(),
                        "apellido" => Yii::$app->user->getApellidoUsuario(), 
                        "efectores" => $efectores,
                        ]
            ];
    }
}
