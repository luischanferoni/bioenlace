<?php
$paramsLocal = __DIR__ . '/params-local.php';
$commonParamsLocal = __DIR__ . '/../../common/config/params-local.php';

$params = \yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/params.php',
    is_file($commonParamsLocal) ? require $commonParamsLocal : [],
    require __DIR__ . '/params.php',
    is_file($paramsLocal) ? require $paramsLocal : []
);

$frontConfig = require __DIR__ . '/../../frontend/config/main.php';

use \yii\web\Request;
$baseUrl = str_replace('/admin/web', '/admin', (new Request)->getBaseUrl());

//$frontModule = \Yii::$app->getModule('frontend');

return [
    'id' => 'bioenlace-admin',
    'language' => 'es',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        \common\components\Platform\Core\Db\EnsureDbConnectionBootstrap::class,
    ],
    'timeZone' => 'America/Argentina/Tucuman',
    'controllerNamespace' => 'admin\controllers',
    'components' => [
        'formatter' => [
            'dateFormat' => 'dd/MM/yyyy',
            'datetimeFormat' => 'php:F jS, Y h:i',
            'timeFormat' => 'php:H:i:s',
            'defaultTimeZone' => 'America/Argentina/Tucuman',
        ],
        'request' => [
            'csrfParam' => '_csrf-admin',
            'baseUrl' => $baseUrl,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'authManager' => [
            'class' => 'common\models\BioenlaceDbManager',
            'efectorAssignmentTable' => 'profesional_efector_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_', 'AdminMinisterio'],
        ],        
        'user' => [
            'class' => 'admin\components\UserConfig',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-bioenlace-admin', 'httpOnly' => true],
        ],
        'frontendUser' => $frontConfig['components']['user'],        
        'errorHandler' => [
            'errorAction' => 'site/error'
        ],        
        'session' => [
            // Nombre de la cookie de sesión para login en admin
            'name' => 'bioenlace-admin',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['warning', 'trace'],
                ],
                [
                    'class' => \common\components\Platform\Infra\Log\ResilientDbTarget::class,
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
                'user-management/auth/activate-account' => 'auth/activate-account',
                'user-management/auth/activate-account-receive/<token:[\w\-]+>' => 'auth/activate-account-receive',
                'user-management/auth/confirm-email' => 'auth/confirm-email',
                'user-management/auth/confirm-email-receive/<token:[\w\-]+>' => 'auth/confirm-email-receive',
                '<controller:\w+>/<id:\d+>' => '<controller>/view',
                '<controller:\w+>/<action:\w+>/<id:\d+>' => '<controller>/<action>',
                '<controller:\w+>/<action:\w+>' => '<controller>/<action>',
            ],            
        ],
        'sisa' => [
            'class' => 'frontend\components\apis\Sisa',
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
                'permission' => 'admin\controllers\LegacyRbacRedirectController',
                'role' => 'admin\controllers\RbacRoleController',
                'auth-item-group' => 'admin\controllers\LegacyRbacRedirectController',
                'user' => 'admin\controllers\UserAccountController',
                'user-permission' => 'admin\controllers\UserRoleController',
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
