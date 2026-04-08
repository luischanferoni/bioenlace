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
    /** @var string Acción por defecto: `/agenda` sirve el mismo partial que el shell fetchea. */
    public $defaultAction = 'crear';

    /**
     * Agenda laboral — HTML sin layout (shell SPA + navegación directa).
     *
     * @spa_presentation inline
     * @native_assets_css /css/scheduler.css
     * @native_assets_js /js/scheduler.js,/js/agenda-laboral.js
     * @mobile_screen_id agenda.crear
     */
    public function actionCrear()
    {
        $tiposDia = ArrayHelper::map(
            Tipo_dia::find()->orderBy(['nombre' => SORT_ASC])->all(),
            'id_tipo_dia',
            'nombre'
        );

        return $this->renderPartial('crear', [
            'tiposDia' => $tiposDia,
        ]);
    }
}
