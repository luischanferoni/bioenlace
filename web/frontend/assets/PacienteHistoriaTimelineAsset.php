<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * HC / timeline paciente ({@see PacienteController::actionHistoria}).
 */
class PacienteHistoriaTimelineAsset extends AssetBundle
{
    /** @var list<class-string<AssetBundle>> */
    public $depends = [
        AppAsset::class,
        BioenlaceApiClientAsset::class,
    ];

    /** @var list<string> */
    public $js = [];

    /** @var list<string> */
    public $css = [];

    public function init(): void
    {
        parent::init();

        $cssAbs = \Yii::getAlias('@frontend/web/css/episodio-historia-banner.css');
        $cssUrl = Url::to('@web/css/episodio-historia-banner.css');
        if (is_file($cssAbs)) {
            $cssUrl .= '?v=' . filemtime($cssAbs);
        }
        $this->css[] = $cssUrl;

        $jsAbs = \Yii::getAlias('@frontend/web/js/paciente-historia-timeline.js');
        $jsUrl = Url::to('@web/js/paciente-historia-timeline.js');
        if (is_file($jsAbs)) {
            $jsUrl .= '?v=' . filemtime($jsAbs);
        }
        $this->js[] = $jsUrl;
    }

    public static function registerWithPlotly(View $view): void
    {
        $view->registerJsFile(
            'https://cdn.plot.ly/plotly-2.27.1.min.js',
            [
                'position' => View::POS_HEAD,
                'charset' => 'utf-8',
            ]
        );
        static::register($view);
    }
}
