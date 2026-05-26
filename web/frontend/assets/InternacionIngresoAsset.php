<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Ingreso a internación vía API v1.
 */
class InternacionIngresoAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/widgets/internacion-ingreso.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];
}
