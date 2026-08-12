<?php

namespace frontend\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Panel site/index (listado clínico / tablero guardia).
 */
class PacientesListadoAsset extends AssetBundle
{
    public $depends = [
        AppAsset::class,
    ];

    public function registerAssetFiles($view)
    {
        $files = [
            'async-consulta-chat.js',
            'pacientes-listado.js',
        ];
        foreach ($files as $file) {
            $abs = \Yii::getAlias('@frontend/web/js/' . $file);
            $url = \yii\helpers\Url::to('@web/js/' . $file, true);
            if (is_file($abs)) {
                $url .= '?v=' . filemtime($abs);
            }
            $view->registerJsFile($url, ['depends' => static::depends]);
        }
    }
}
