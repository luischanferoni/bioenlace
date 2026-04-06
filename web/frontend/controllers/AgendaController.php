<?php

namespace frontend\controllers;

use common\models\Tipo_dia;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

/**
 * UI web (HTML) para agendas laborales por servicio.
 *
 * No confundir con {@see \frontend\modules\api\v1\controllers\AgendaController}: ese es el módulo API (JSON).
 * Aquí solo se renderiza la vista; persistencia vía `/api/v1/agenda/*`.
 */
class AgendaController extends Controller
{
    public function actionIndex()
    {
        $this->layout = 'blanco';
        $tiposDia = ArrayHelper::map(
            Tipo_dia::find()->orderBy(['nombre' => SORT_ASC])->all(),
            'id_tipo_dia',
            'nombre'
        );

        return $this->render('index', [
            'tiposDia' => $tiposDia,
        ]);
    }
}
