<?php

namespace common\assets;

use yii\web\AssetBundle;

class AuthLoginAsset extends AssetBundle
{
    public $sourcePath = '@common/web';

    public $js = [
        'js/login-auth.js',
    ];
}
