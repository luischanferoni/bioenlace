<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Lógica de la página de alta web (`/alta`): QR, código y consulta de estado.
 */
class AltaVinculoAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'js/alta-vinculo.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
