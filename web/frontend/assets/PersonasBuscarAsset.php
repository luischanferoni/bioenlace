<?php

namespace frontend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;

/**
 * Búsqueda de personas (QR / Renaper) — form `#buscar`.
 */
class PersonasBuscarAsset extends AssetBundle
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
        $abs = \Yii::getAlias('@frontend/web/js/personas-buscar.js');
        $url = Url::to('@web/js/personas-buscar.js');
        if (is_file($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        $this->js[] = $url;
    }
}
