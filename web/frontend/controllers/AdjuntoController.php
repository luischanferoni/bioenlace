<?php

namespace frontend\controllers;

use Yii;

use yii\data\ActiveDataProvider;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\UnprocessableEntityHttpException;
use yii\filters\VerbFilter;
use yii\helpers\BaseFileHelper;

use common\models\Adjunto;

/**
 * AdjuntoController implements the CRUD actions for Adjunto model.
 */
class AdjuntoController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
           /* 'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],    */        
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionVer($id)
    {
        $adjunto = Adjunto::findOne($id);
        
        if ($adjunto === null) {
            throw new \yii\web\NotFoundHttpException('El archivo no existe.');
        }

        $path = Yii::getAlias('@webroot') . '/'. $adjunto->path;
        $extension = explode(".", $adjunto->path);

        if (!file_exists($path)) {
            throw new \yii\web\NotFoundHttpException('El archivo no existe.');
        }
        
        $response = Yii::$app->getResponse();
        $response->headers->set('Content-Type', BaseFileHelper::getMimeType($path));
        $response->format = \yii\web\Response::FORMAT_RAW;
        if ( !is_resource($response->stream = fopen($path, 'r')) ) {
            throw new \yii\web\ServerErrorHttpException('Ocurrió un error con el adjunto');
        }
        return $response->send();
    }

}