<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Tablero de guardia en inicio (site/index con encounter EMER).
 */
class EmergencyBoardAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $css = [
        'css/emergency-board.css',
    ];

    public $js = [
        'js/bioenlace-triage-vitals.js',
    ];

    public $depends = [
        AppAsset::class,
    ];
}
