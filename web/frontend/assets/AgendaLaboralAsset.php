<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Agenda laboral (vista Yii): grid semanal {@see @web/js/scheduler.js} + lógica {@see @web/js/agenda-laboral.js}.
 */
class AgendaLaboralAsset extends AssetBundle
{
    public $basePath = '@webroot';

    public $baseUrl = '@web';

    public $css = [
        'css/scheduler.css',
    ];

    public $js = [
        'js/scheduler.js',
        'js/agenda-laboral.js',
    ];

    public $depends = [
        \yii\web\JqueryAsset::class,
    ];
}
