<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;

/**
 * API motivos de consulta (mensajes, envío, subida de archivos).
 */
class MotivosConsultaController extends BaseController
{
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

    /**
     * GET mensajes de motivos de una consulta.
     */
    public function actionMessages($consulta_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaMotivosChatController('consulta-motivos-chat', Yii::$app);
        return $controller->runAction('messages', ['id' => $consulta_id]);
    }

    /**
     * POST enviar mensaje de texto.
     */
    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaMotivosChatController('consulta-motivos-chat', Yii::$app);
        return $controller->runAction('send', []);
    }

    /**
     * POST subir archivo (imagen o audio).
     */
    public function actionUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $controller = new \frontend\controllers\ConsultaMotivosChatController('consulta-motivos-chat', Yii::$app);
        return $controller->runAction('upload', []);
    }
}
