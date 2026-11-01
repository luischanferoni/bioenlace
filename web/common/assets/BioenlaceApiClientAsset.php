<?php

namespace common\assets;

use yii\web\AssetBundle;

/**
 * Cliente JS común para llamadas a la API web.
 * Cargar antes de spa-home, ajax-wrapper, wizards API, etc. (el layout ya define `getBioenlaceApiClientHeaders`).
 */
class BioenlaceApiClientAsset extends AssetBundle
{
    public $sourcePath = '@common/web';

    public $js = [
        'js/bioenlace-api-client.js',
    ];
}
