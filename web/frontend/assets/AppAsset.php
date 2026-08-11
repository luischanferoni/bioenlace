<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = "@frontend/web/custom-template";
    
    public $css = [
        // Bootstrap 5 CSS personalizado (solo para frontend)
        'css/bootstrap.min.css',
        'css/bootstrap-custom.css',
    ];
    
    public $js = [
        // Bootstrap 5 JavaScript personalizado (solo para frontend)
        'js/bootstrap.bundle.min.js',
        'js/bootstrap-custom.js',
    ];
    
    // Archivos JS adicionales desde @web/js (fuera del sourcePath)
    public function init()
    {
        parent::init();
        
        // Agregar archivos JS desde @web/js
        // Estos archivos están en frontend/web/js/, no en custom-template
        // Usar Url::to para generar las URLs correctas
        $jsFiles = [
            'ajax-wrapper.js',
            // Bridge reutilizable para páginas nativas (tipo 1)
            'native-page-bridge.js',
            'bioenlace-triage-vitals.js',
            'turnos.js',
            'encounter-capture-review.js',
            'timeline.js',
            'encounter-capture-form.js',
        ];
        
        $bustCache = [
            'encounter-capture-review.js' => true,
            'encounter-capture-form.js' => true,
        ];
        foreach ($jsFiles as $file) {
            // Usar Url::to para generar la URL correcta desde @web
            $url = \yii\helpers\Url::to('@web/js/' . $file, true);
            if (isset($bustCache[$file])) {
                $abs = \Yii::getAlias('@frontend/web/js/' . $file);
                $url .= '?v=' . (is_file($abs) ? filemtime($abs) : time());
            }
            $this->js[] = $url;
        }
    }

    public $depends = [
        '\common\assets\AppAsset',
        BioenlaceApiClientAsset::class,
    ];
}
