<?php


namespace frontend\assets;

use yii\web\AssetBundle;


class FormWizardAsset extends AssetBundle
{
    public $sourcePath = "@common/web";

    public $js = [
        'template/js/plugins/form-wizard.js',
    ];

    public $depends = [        
        '\yii\web\JqueryAsset'        
    ];
}