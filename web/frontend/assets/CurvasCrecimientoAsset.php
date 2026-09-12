<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Curvas de crecimiento Plotly ({@see PersonasController} curvas).
 */
class CurvasCrecimientoAsset extends AssetBundle
{
    /** @var list<class-string<AssetBundle>> */
    public $depends = [
        AppAsset::class,
    ];

    /** @var list<string> */
    public $js = [];

    public function init(): void
    {
        parent::init();
        $abs = \Yii::getAlias('@frontend/web/js/curvas-crecimiento.js');
        $url = Url::to('@web/js/curvas-crecimiento.js');
        if (is_file($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        $this->js[] = $url;
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
