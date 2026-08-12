<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * Renderizador UI JSON embebido (modales / páginas nativas).
 */
class BioenlaceUiJsonEmbedAsset extends AssetBundle
{
    public $sourcePath = '@frontend/web';

    public $js = [
        'js/bioenlace-ui-json-embed.js',
    ];

    public $depends = [
        BioenlaceApiClientAsset::class,
    ];

    public function init()
    {
        parent::init();
        $abs = \Yii::getAlias('@frontend/web/js/bioenlace-ui-json-embed.js');
        if (is_file($abs)) {
            $this->js[0] .= '?v=' . filemtime($abs);
        }
    }
}
