<?php

namespace common\assets;

use yii\web\AssetBundle;

class AuthLoginAsset extends AssetBundle
{
    public $sourcePath = '@common/web';

    public $js = [
        'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
        'js/login-auth.js',
    ];
}
