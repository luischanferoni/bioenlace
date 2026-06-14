<?php
return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'container' => [
        'definitions' => [
            'yii\grid\GridView' => [
                'pager' => [
                    'class' => \yii\bootstrap5\LinkPager::class,
                    'prevPageLabel' => 'ANTERIOR',
                    'nextPageLabel' => 'SIGUIENTE',
                    'options' => ['class' => 'pagination justify-content-center mt-5']
                ]
            ],
        ],
    ],
    'components' => [
        'formatter' => [
            'dateFormat' => 'dd/MM/yyyy',
            'datetimeFormat' => 'php:F jS, Y h:i',
            'timeFormat' => 'php:H:i:s',
            'defaultTimeZone' => 'America/Argentina/Tucuman',
        ],        
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'assetManager' => [
            'bundles' => [                
                'kartik\select2\ThemeDefaultAsset' => ['css' => [], 'js' => []],
                //'kartik\select2\ThemeBootstrapAsset' => ['css' => [], 'js' => []],
                'kartik\select2\Select2Asset' => ['css' => [], 'js' => []],
                'kartik\select2\Select2KrajeeAsset' => ['css' => []],
                //'kartik\select2\ThemeKrajeeBs5Asset' => ['sourcePath'=>'@common/web', 'css' => ['css/select2.css']],
                
                'yii\bootstrap5\BootstrapAsset' => ['css' => []],
                'yii\bootstrap5\BootstrapPluginAsset' => ['js' => []],
                'yii\web\JqueryAsset' => [
                    'sourcePath'=>'@common/web',
                    'js' => ['template/js/core/libs.min.js']],
                'wbraganca\dynamicform\DynamicFormAsset' => [
                        'sourcePath'=>'@common/web',
                        'js' => ['js\yii2-dynamic-form.js']],
                //'wbraganca\dynamicform\DynamicFormAsset' => false,
            ]
        ],
        'iamanager' => [
            'class' => 'common\\components\\Ai\\IAManager',
        ],
        'mpi' => [
            'class' => \common\components\Domain\Integrations\Mpi\MpiApiClient::class,
        ],
        'snowstorm' => [
            'class' => \common\components\Domain\Terminology\Snomed\SnowstormClient::class,
        ],
        'mailer' => require __DIR__ . '/mailer.php',
    ],
];
