<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;

/**
 * Lista de espera de turnos ({@see TurnosController::actionEspera}).
 */
class TurnosEsperaAsset extends AssetBundle
{
    /** @var list<class-string<AssetBundle>> */
    public $depends = [
        AppAsset::class,
        BioenlaceApiClientAsset::class,
    ];

    /** @var list<string> */
    public $js = [];

    public function init(): void
    {
        parent::init();
        $abs = \Yii::getAlias('@frontend/web/js/turnos-espera.js');
        $url = Url::to('@web/js/turnos-espera.js');
        if (is_file($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        $this->js[] = $url;
    }
}
