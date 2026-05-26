<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Mapa de camas e indicadores de internación (vista index).
 */
class InternacionMapaAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/widgets/internacion-mapa.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];
}
