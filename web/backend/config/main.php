<?php
$paramsLocal = __DIR__ . '/params-local.php';

$params = \yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    is_file($paramsLocal) ? require $paramsLocal : []
);

$frontConfig = require __DIR__ . '/../../frontend/config/main.php';

use \yii\web\Request;
$baseUrl = str_replace('/backend/web', '/admin', (new Request)->getBaseUrl());

//$frontModule = \Yii::$app->getModule('frontend');

return [
    'id' => 'bioenlace-backend',
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
            'class' => 'common\models\BioenlaceDbManager',
            'efectorAssignmentTable' => 'profesional_efector_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_'],
        ],        
        'user' => [
            'class' => 'backend\components\UserConfig',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-bioenlace-backend', 'httpOnly' => true],
        ],
        'frontendUser' => $frontConfig['components']['user'],        
        'errorHandler' => [
            'errorAction' => 'site/error'
        ],        
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name' => 'bioenlace-backend',
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
                'site/login' => 'auth/login',
                'login' => 'auth/login',
                'user-management/auth/login' => 'auth/login',
                'user-management/auth/logout' => 'auth/logout',
                'user-management/auth/change-own-password' => 'auth/change-own-password',
                'user-management/auth/password-recovery' => 'auth/password-recovery',
                'user-management/auth/password-recovery-receive/<token:[\w\-]+>' => 'auth/password-recovery-receive',
                'user-management/auth/confirm-email' => 'auth/confirm-email',
                'user-management/auth/confirm-email-receive/<token:[\w\-]+>' => 'auth/confirm-email-receive',
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
                'pathMap' => [],
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
            'class' => \common\modules\UserManagementCompatModule::class,
            'controllerMap' => [
                'auth' => 'frontend\controllers\userManagement\AuthController',
                'permission' => 'backend\controllers\LegacyRbacRedirectController',
                'role' => 'backend\controllers\LegacyRbacRedirectController',
                'auth-item-group' => 'backend\controllers\LegacyRbacRedirectController',
                'user' => 'backend\controllers\UserAccountController',
                'user-permission' => 'backend\controllers\UserRoleController',
            ],
            //'registrationFormClass' => 'app\models\User',
            // 'enableRegistration' => true,
            // Here you can set your handler to change layout for any controller or action
            // Tip: you can use this event in any module
            'on beforeAction' => function(yii\base\ActionEvent $event) {
                if ($event->action->uniqueId === 'auth/login') {
                    $event->action->controller->layout = '@frontend/views/layouts/loginLayout.php';
                }
            },
        ],
        'frontend' => ['class' => \frontend\Module::class],
        'api' => ['class' => \frontend\modules\api\v1\Module::class]
        //'gridview' => [
        //    'class' => 'kartik\grid\Module',
        //]        
    ],
    'aliases' => [
        '@kartik/switchinput' => '@vendor/kartik-v/yii2-widget-switchinput',
        '@bower' => '@vendor/bower-asset',
    ],        
];
