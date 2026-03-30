<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use common\models\Persona;

class PersonaController extends Controller
{
    public $modelClass = 'common\models\Persona';

    /** Acciones sin auth para API (leído por BaseController de la API). */
    public static $authenticatorExcept = ['timeline'];

    /**
     * Respuesta de éxito estándar (copiada de BaseController para uso en frontend).
     */
    protected function success($data = null, $message = 'Operación exitosa', $code = 200)
    {
        Yii::$app->response->statusCode = $code;
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Respuesta de error estándar (copiada de BaseController para uso en frontend).
     */
    protected function error($message = 'Error en la operación', $errors = null, $code = 400)
    {
        Yii::$app->response->statusCode = $code;
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }
}

