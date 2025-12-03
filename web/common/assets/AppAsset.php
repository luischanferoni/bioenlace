<?php


namespace common\assets;

use yii\web\AssetBundle;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $sourcePath = "@common/web";
    public $css = [
        // Fuentes
        '//fonts.googleapis.com/css2?family=Heebo:wght@100;200;300;400;500;600;700;800;900&display=swap',
        
        // Componentes específicos (mantener solo los necesarios)
        'template/vendor/flatpickr/dist/flatpickr.min.css',
        'template/vendor/sweetalert2/dist/sweetalert2.min.css',
        
        // CSS personalizado
        'css/site.css',
    ];
    public $js = [
        // Librerías específicas (mantener solo las necesarias)
        'template/vendor/lodash/lodash.min.js',
        'template/vendor/sweetalert2/dist/sweetalert2.min.js',
        'template/js/plugins/sweet-alert.js',
        'template/vendor/flatpickr/dist/flatpickr.min.js',
        'template/js/plugins/flatpickr.js',
        
        // Select2 (mantener para compatibilidad)
        '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js',
        
        // Scripts personalizados
        'js/main.js',
        ['js/sisse_begin.js', 'position' => \yii\web\View::POS_BEGIN],        
        'js/onscan/onscan.min.js'
    ];

    public $depends = [        
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapIconAsset',
    ];
}
