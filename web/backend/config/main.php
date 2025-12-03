<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

$frontConfig = require __DIR__ . '/../../frontend/config/main.php';

use \yii\web\Request;
$baseUrl = str_replace('/backend/web', '/admin', (new Request)->getBaseUrl());

//$frontModule = \Yii::$app->getModule('frontend');

return [
    'id' => 'sisse-backend',
    'language' => 'es',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'America/Argentina/Tucuman',
    'controllerNamespace' => 'backend\controllers',
    'components' => [
        'formatter' => [
            'dateFormat' => 'dd/MM/yyyy',
            'datetimeFormat' => 'php:F jS, Y h:i',
            'timeFormat' => 'php:H:i:s',
            'defaultTimeZone' => 'America/Argentina/Tucuman',
        ],
	   'jwt' => [
      		'class' => \sizeg\jwt\Jwt::class,
      		'key'   => 'yt14zxFvJUdIXnOIHP87TpfR42JKyi6Ni2wUX5JoHpLiLtikL1p7vdHWcvGIpCfK',
    	],        
        'request' => [
            'csrfParam' => '_csrf-backend',
            'baseUrl' => $baseUrl,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'authManager' => [
            'class' => 'common\models\SisseDbManager',
            'efectorAssignmentTable' => 'rrhh_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_'],
        ],        
        'user' => [
            'class' => 'backend\components\UserConfig',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-sisse-backend', 'httpOnly' => true],
        ],
        'frontendUser' => $frontConfig['components']['user'],        
        'errorHandler' => [
            'errorAction' => 'site/error'
        ],        
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'sisse-backend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['warning', 'trace'],
                ],
				[
                    'class' => 'yii\log\DbTarget',
                    'levels' => ['error'],
                ],
            ],
        ],
        'urlManager' => [
            'class' => 'yii\web\UrlManager',
            // Disable index.php
            'showScriptName' => false,
            // Disable r= routes
            'enablePrettyUrl' => true,
            'rules' => [  
                '<controller:\w+>/<id:\d+>' => '<controller>/view',
                '<controller:\w+>/<action:\w+>/<id:\d+>' => '<controller>/<action>',
                '<controller:\w+>/<action:\w+>' => '<controller>/<action>',
            ],            
        ],
        'snowstorm' => [
            'class' => 'frontend\components\Snowstorm',
        ],
        'suri' => [
            'class' => 'frontend\components\Suri',
        ], 
        'ips' => [
            'class' => 'frontend\components\Ips',
        ],
        'sisa' => [
            'class' => 'frontend\components\apis\Sisa',
        ],
        'view' => [
            'theme' => [
                'pathMap' => [
                    '@vendor/webvimark/module-user-management/views/user' => '@backend/views/user-management/user',
                    '@vendor/webvimark/module-user-management/views/user-permission' => '@backend/views/user-management/user-permission',                    
                    '@vendor/webvimark/module-user-management/views/role' => '@backend/views/user-management/role',
                    '@vendor/webvimark/module-user-management/views/permission' => '@backend/views/user-management/permission',
                    '@vendor/webvimark/module-user-management/views/auth-item-group' => '@backend/views/user-management/auth-item-group',
                ],
            ],
        ],
    ],
    'params' => $params,
    'modules' => [
        'gridview' => [
            'class' => 'kartik\grid\Module',
            // other module settings
        ],
        'reportico' => [
            'class' => 'reportico\reportico\Module' ,
            'controllerMap' => [
                            'reportico' => 'reportico\reportico\controllers\ReporticoController',
                            'mode' => 'reportico\reportico\controllers\ModeController',
                            //'ajax' => 'reportico\reportico\controllers\AjaxController',
                        ]
            ],
        'user-management' => [
            'class' => 'webvimark\modules\UserManagement\UserManagementModule',
            //'registrationFormClass' => 'app\models\User',
            // 'enableRegistration' => true,
            // Here you can set your handler to change layout for any controller or action
            // Tip: you can use this event in any module
            'on beforeAction' => function(yii\base\ActionEvent $event) {
                if ($event->action->uniqueId == 'user-management/auth/login') {
                    $event->action->controller->layout = '@frontend/views/layouts/loginLayout.php';
                };
            },
        ],
        'frontend' => ['class' => \frontend\Module::class]
        //'gridview' => [
        //    'class' => 'kartik\grid\Module',
        //]        
    ],
    'aliases' => [
        '@kartik/switchinput' => '@vendor/kartik-v/yii2-widget-switchinput',
        '@bower' => '@vendor/bower-asset',
    ],        
];
