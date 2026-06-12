<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Tablero de guardia en inicio (site/index con encounter EMER).
 */
class GuardiaTableroAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $css = [
        'css/guardia-tablero.css',
    ];

    public $depends = [
        AppAsset::class,
    ];
}
