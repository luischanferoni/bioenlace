<?php

namespace common\assets;

use yii\web\AssetBundle;

/**
 * Registro de paciente staff (lector DNI + Didit) — admin y frontend operativo.
 */
class RegistroPacienteStaffAsset extends AssetBundle
{
    public $sourcePath = '@common/web';

    public $js = [
        'js/admin/registro-paciente-staff.js',
    ];

    public $depends = [
        'common\assets\AppAsset',
    ];
}
