<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;

/**
 * API chat de consulta (mensajes, envío, subida, estado).
 */
class ConsultaChatController extends BaseController
{
    public $modelClass = '';
    public $enableCsrfValidation = false;
    protected $_verbs = ['GET', 'POST', 'OPTIONS'];

    public function behaviors()
    {
        return parent::behaviors();
    }

    public function actionOptions()
    {
        if (Yii::$app->getRequest()->getMethod() !== 'OPTIONS') {
            Yii::$app->getResponse()->setStatusCode(405);
        }
        Yii::$app->getResponse()->getHeaders()->set('Allow', implode(', ', $this->_verbs));
    }

    public function actionMessages($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaChatController('consulta-chat', Yii::$app);
        return $controller->runAction('messages', ['id' => $consulta_id]);
    }

    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaChatController('consulta-chat', Yii::$app);
        return $controller->runAction('send', []);
    }

    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaChatController('consulta-chat', Yii::$app);
        return $controller->runAction('upload', []);
    }

    public function actionStatus($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaChatController('consulta-chat', Yii::$app);
        return $controller->runAction('status', ['id' => $consulta_id]);
    }
}
