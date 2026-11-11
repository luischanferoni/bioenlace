<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Página del teléfono del alta web: código de emparejamiento y reintento del resultado.
 */
class AltaVinculoMovilAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'js/alta-vinculo-movil.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
