<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

use \yii\web\Request;
$baseUrl = str_replace('/frontend/web', '', (new Request)->getBaseUrl());

return [
    'id' => 'bioenlace-frontend',
    'language' => 'es',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'America/Argentina/Tucuman',
    'controllerNamespace' => 'frontend\controllers',    
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
            'csrfParam' => '_csrf-frontend',
            'baseUrl' => $baseUrl,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error'
        ],        
        'authManager' => [
            'class' => 'common\models\SisseDbManager',
            'efectorAssignmentTable' => 'rrhh_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_'],
        ],
        'user' => [
            'class' => 'frontend\components\UserConfig',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-vitamind-frontend', 'httpOnly' => true],            
            // Comment this if you don't want to record user logins
            'on afterLogin' => function ($event) {
                \webvimark\modules\UserManagement\models\UserVisitLog::newVisitor($event->identity->id);
                \frontend\controllers\SiteController::despuesDeLogin();
            },
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'sisse-frontend',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['warning'],
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
                // mensajes
                'GET api/<version:\w+>/messages' => '<version>/chat/index',                
                'OPTIONS api/<version:\w+>/messages' => '<version>/chat/index',

                // chat turnos
                'POST api/<version:\w+>/messages/enviar' => '<version>/chat/recibir',
                'OPTIONS api/<version:\w+>/messages/enviar' => '<version>/chat/recibir',
                // analisis de la consulta
                'POST api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                'OPTIONS api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',

                // sign up
                'POST api/<version:\w+>/signup' => '<version>/signup',
                //'OPTIONS api/<version:\w+>/signup' => '<version>/signup/recibir',
                // login
                'POST api/<version:\w+>/login' => '<version>/login/login',
                // generar token de prueba para paciente por DNI
                'POST api/<version:\w+>/auth/generate-test-token' => '<version>/auth/generate-test-token',
                'GET api/<version:\w+>/auth/generate-test-token' => '<version>/auth/generate-test-token',
                'OPTIONS api/<version:\w+>/login' => '<version>/login/login',

                // consulta-chat - chat médico
                'GET api/<version:\w+>/consulta-chat/messages/<id:\d+>' => '<version>/consulta-chat/messages',
                'OPTIONS api/<version:\w+>/consulta-chat/messages/<id:\d+>' => '<version>/consulta-chat/messages',
                'POST api/<version:\w+>/consulta-chat/send' => '<version>/consulta-chat/send',
                'OPTIONS api/<version:\w+>/consulta-chat/send' => '<version>/consulta-chat/send',
                'GET api/<version:\w+>/consulta-chat/status/<id:\d+>' => '<version>/consulta-chat/status',
                'OPTIONS api/<version:\w+>/consulta-chat/status/<id:\d+>' => '<version>/consulta-chat/status',
                
                // Turnos API
                'GET api/<version:\w+>/turnos' => '<version>/turnos/index',
                'OPTIONS api/<version:\w+>/turnos' => '<version>/turnos/index',
                'GET api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/view',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/view',
                'POST api/<version:\w+>/turnos' => '<version>/turnos/create',
                'OPTIONS api/<version:\w+>/turnos' => '<version>/turnos/create',
                'PUT api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/update',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/update',
                
                // Personas Timeline API
                'GET api/<version:\w+>/personas/<id:\d+>/timeline' => '<version>/persona/timeline',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/timeline' => '<version>/persona/timeline',
                
                // Config API
                'GET api/<version:\w+>/config/efectores' => '<version>/config/efectores',
                'OPTIONS api/<version:\w+>/config/efectores' => '<version>/config/efectores',
                'GET api/<version:\w+>/config/servicios' => '<version>/config/servicios',
                'OPTIONS api/<version:\w+>/config/servicios' => '<version>/config/servicios',
                'GET api/<version:\w+>/config/encounter-classes' => '<version>/config/encounter-classes',
                'OPTIONS api/<version:\w+>/config/encounter-classes' => '<version>/config/encounter-classes',
                'POST api/<version:\w+>/config/set-session' => '<version>/config/set-session',
                'OPTIONS api/<version:\w+>/config/set-session' => '<version>/config/set-session',
                
                // CRUD API
                'POST api/<version:\w+>/crud/process-query' => '<version>/crud/process-query',
                'OPTIONS api/<version:\w+>/crud/process-query' => '<version>/crud/process-query',
                
                // Audio API (Speech-to-Text)
                'POST api/<version:\w+>/audio/transcribir' => '<version>/audio/transcribir',
                'OPTIONS api/<version:\w+>/audio/transcribir' => '<version>/audio/transcribir',
            ],            
        ],
        'snowstorm' => [
            'class' => 'frontend\components\Snowstorm',
        ], 
        'imagenes' => [
            'class' => 'frontend\components\Pentalogic',
        ],
        'sianlabs' => [
            'class' => 'frontend\components\apis\Sianlabs',
        ],
        'forms' => [
            'class' => 'frontend\components\apis\Forms',
        ],
        'deepSeek' => [
            'class' => 'frontend\components\apis\DeepSeek',
        ],        
        'authClientCollection' => [
            'class' => 'yii\authclient\Collection',
            'clients' => [
                'sianlabs' => [
                    'class' => 'frontend\components\apis\Sianlabs',
                    'clientId' => 2,
                    'clientSecret' => 'NmB3hxSzUBpDnCcg7zbcDxvy4Ior02HVeTDuUQcb',
                ],        
            ],
        ],        
    ],
    'params' => $params,
    'modules' => [
        'user-management' => [
            'class' => 'webvimark\modules\UserManagement\UserManagementModule',
            //'registrationFormClass' => 'common\models\User',
            // 'enableRegistration' => true,
            // Here you can set your handler to change layout for any controller or action
            // Tip: you can use this event in any module
            'on beforeAction' => function (yii\base\ActionEvent $event) {
                if ($event->action->uniqueId == 'user-management/auth/login') {
                    $event->action->controller->layout = '@frontend/views/layouts/loginLayout.php';
                };
            },
        ],
        'gridview' => [
            'class' => 'kartik\grid\Module',
        ],
        'v1' => [
            'class' => 'frontend\modules\api\v1\Module',
            'basePath' => '@frontend/modules/api/v1',
            'controllerNamespace' => 'frontend\modules\api\v1\controllers',
        ],           
    ],
    'aliases' => [
        '@kartik/switchinput' => '@vendor/kartik-v/yii2-widget-switchinput',
        '@bower' => '@vendor/bower-asset',
    ],        
];