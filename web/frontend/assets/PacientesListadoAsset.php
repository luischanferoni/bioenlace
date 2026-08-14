<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Panel site/index (listado clínico / tablero guardia).
 */
class PacientesListadoAsset extends AssetBundle
{
    /** @var list<class-string<AssetBundle>> */
    public $depends = [
        AppAsset::class,
        \common\assets\DniBarcodeAsset::class,
    ];

    /** @var list<string> */
    public $js = [];

    /**
     * @param list<class-string<AssetBundle>> $extraDepends
     */
    public static function registerWithDepends(View $view, array $extraDepends = []): void
    {
        $bundle = new static();
        $bundle->depends = array_values(array_merge([AppAsset::class], $extraDepends));
        $bundle->register($view);
    }

    public function init(): void
    {
        parent::init();

        $files = [
            'widgets/dni-barcode.js',
            'async-consulta-chat.js',
            'pacientes-listado.js',
        ];

        foreach ($files as $file) {
            $abs = \Yii::getAlias('@frontend/web/js/' . $file);
            $url = Url::to('@web/js/' . $file);
            if (is_file($abs)) {
                $url .= '?v=' . filemtime($abs);
            }
            $this->js[] = $url;
        }
    }
}
