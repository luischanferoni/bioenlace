<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use Exception;

use sizeg\jwt\Jwt;
use sizeg\jwt\JwtHttpBearerAuth;
use yii\filters\auth\HttpHeaderAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\httpclient\Client;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

use frontend\components\Mpi;

use common\models\Persona;
use common\models\Domicilio;
use common\models\Persona_domicilio;


class SyncController extends \yii\rest\Controller
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
                'Origin' => ['http://localhost:3000', 'http://localhost:50253', 'https://riesgo-dbt.msalsgo.gob.ar'], // restrict access to
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

        // comento la AUTENTICACION solo para prueba, sino da unauthorized

        /*
        // re-add authentication filter if you are using it.
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method) if using authentication filter.
        $behaviors['authenticator']['except'] = ['options', 'login'];
        */
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

    public function actionPrueba()
    {

    }

}
