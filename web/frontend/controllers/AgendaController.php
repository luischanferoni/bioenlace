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

    /**
     * Agenda laboral (web nativa) + metadata para catálogo de intents.
     *
     * @native_ui_path /agenda/embed
     * @spa_presentation inline
     * @native_assets_css /css/scheduler.css
     * @native_assets_js /js/scheduler.js,/js/agenda-laboral.js
     * @mobile_screen_id agenda.index
     */
    public function actionIndex()
    {
        // No usar layout `blanco`: no ejecuta head()/endBody() y los assets (jQuery, agenda-laboral.js) no se cargan.
        $this->layout = 'main_sinmenuizquierda';
        $tiposDia = ArrayHelper::map(
            Tipo_dia::find()->orderBy(['nombre' => SORT_ASC])->all(),
            'id_tipo_dia',
            'nombre'
        );

        return $this->render('index', [
            'tiposDia' => $tiposDia,
        ]);
    }

    /**
     * Fragmento embebible (sin layout) para shell SPA.
     * Devuelve solo el HTML del componente; los assets deben ser cargados por el caller.
     */
    public function actionEmbed()
    {
        $tiposDia = ArrayHelper::map(
            Tipo_dia::find()->orderBy(['nombre' => SORT_ASC])->all(),
            'id_tipo_dia',
            'nombre'
        );

        return $this->renderPartial('_embed', [
            'tiposDia' => $tiposDia,
        ]);
    }
}
