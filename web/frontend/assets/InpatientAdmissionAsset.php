<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Ingreso a internación vía API v1.
 */
class InpatientAdmissionAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/widgets/inpatient-admission.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];
}
