<?php

namespace frontend\assets;

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
    ];

    /**
     * @param list<class-string<AssetBundle>> $extraDepends
     */
    public static function registerWithDepends(View $view, array $extraDepends = []): void
    {
        $bundle = new static();
        $bundle->depends = array_values(array_merge([AppAsset::class], $extraDepends));
        $bundle->register($view);
    }

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
            $view->registerJsFile($url, ['depends' => $this->depends]);
        }
    }
}
