<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Estilos y scripts del shell de login / auth (`loginLayout`).
 */
class LoginAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/login.css',
    ];
    public $depends = [
        AppAsset::class,
        \common\assets\AuthLoginAsset::class,
    ];
}
