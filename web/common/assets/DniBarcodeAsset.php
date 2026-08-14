<?php

namespace common\assets;

use yii\web\AssetBundle;

/**
 * Lector hardware de código PDF417 del DNI (onScan).
 */
class DniBarcodeAsset extends AssetBundle
{
    public $sourcePath = '@common/web';

    /** @var list<string> */
    public $js = [
        'js/onscan/onscan.js',
    ];

    /** @var list<class-string<AssetBundle>> */
    public $depends = [
        AppAsset::class,
    ];
}
