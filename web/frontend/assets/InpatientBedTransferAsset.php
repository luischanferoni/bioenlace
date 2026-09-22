<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Cambio de cama de internación vía API v1.
 */
class InpatientBedTransferAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/widgets/internacion-cambio-cama.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];
}
