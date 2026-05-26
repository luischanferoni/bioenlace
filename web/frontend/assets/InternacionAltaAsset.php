<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Alta estructurada de internación vía API v1.
 */
class InternacionAltaAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/widgets/internacion-alta.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];
}
