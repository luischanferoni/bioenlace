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
            'turnos.js',
            'chat-inteligente.js',
            'timeline.js'
        ];
        
        foreach ($jsFiles as $file) {
            // Usar Url::to para generar la URL correcta desde @web
            $this->js[] = \yii\helpers\Url::to('@web/js/' . $file, true);
        }
    }

    public $depends = [        
        '\common\assets\AppAsset',   
    ];
}
