<?php

namespace frontend\controllers;

use yii\web\Controller;

/**
 * Atenciones de enfermería — vista modal desde ficha de persona.
 */
class AtencionesEnfermeriaController extends Controller
{
    /**
     * @param integer $id id_persona
     * @no_intent_catalog
     */
    public function actionView($id)
    {
        return $this->renderAjax('/consulta-atenciones-enfermeria/view', [
            'model' => \common\models\Persona::findOne($id),
        ]);
    }
}
