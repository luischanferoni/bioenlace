<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Wizard post-login (efector / encounter / servicio) + POST sesion-operativa/establecer.
 */
class SesionOperativaPostloginWizardAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/sesion-operativa-postlogin-wizard.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
        FormWizardAsset::class,
    ];
}
