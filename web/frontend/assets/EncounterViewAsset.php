<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;

/**
 * Pantalla solo lectura de documentación clínica ({@see PacienteController::actionEncounterDocumentation}).
 */
class EncounterViewAsset extends AssetBundle
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
        $abs = \Yii::getAlias('@frontend/web/js/encounter-view.js');
        $url = Url::to('@web/js/encounter-view.js');
        if (is_file($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        $this->js[] = $url;
    }
}
