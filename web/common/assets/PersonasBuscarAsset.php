<?php

namespace common\assets;

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

    public $sourcePath = '@common/web';

    public $js = [
        'js/personas-buscar.js',
    ];
}
