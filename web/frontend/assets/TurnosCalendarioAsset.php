<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;

/**
 * Calendario de turnos operativo ({@see views/turnos/_calendario.php}).
 */
class TurnosCalendarioAsset extends AssetBundle
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
        $abs = \Yii::getAlias('@frontend/web/js/turnos_calendario.js');
        $url = Url::to('@web/js/turnos_calendario.js');
        if (is_file($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        $this->js[] = $url;
    }
}
