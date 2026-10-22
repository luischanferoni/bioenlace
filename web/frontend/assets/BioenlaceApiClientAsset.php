<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * JS común: {@see web/frontend/web/js/bioenlace-api-client.js} (resolución de URL y descarga autenticada de PDF).
 * Cargar antes de spa-home, ajax-wrapper, wizards API, etc. (el layout ya define `getBioenlaceApiClientHeaders`).
 */
class BioenlaceApiClientAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/bioenlace-api-client.js',
    ];
}
