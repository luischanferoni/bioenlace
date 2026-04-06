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
    'bootstrap' => [
        'log',
        \frontend\components\EnforceGhostAccessBootstrap::class,
    ],
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
            'class' => 'common\models\BioenlaceDbManager',
            'efectorAssignmentTable' => 'rrhh_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_'],
        ],
        'user' => [
            'class' => 'frontend\components\UserConfig',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-bioenlace-frontend', 'httpOnly' => true],            
            // Comment this if you don't want to record user logins
            'on afterLogin' => function ($event) {
                \webvimark\modules\UserManagement\models\UserVisitLog::newVisitor($event->identity->id);
                \frontend\controllers\SiteController::despuesDeLogin();
            },
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'bioenlace-frontend',
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
                // Asistente (canal chat)
                'GET api/<version:\w+>/asistente/estado' => '<version>/chat/estado',
                'OPTIONS api/<version:\w+>/asistente/estado' => '<version>/chat/estado',
                'POST api/<version:\w+>/asistente/enviar' => '<version>/chat/recibir',
                'OPTIONS api/<version:\w+>/asistente/enviar' => '<version>/chat/recibir',
                // analisis de la consulta
                'POST api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                'OPTIONS api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                // guardar consulta completa
                'POST api/<version:\w+>/consulta/guardar' => '<version>/consulta/guardar',
                'OPTIONS api/<version:\w+>/consulta/guardar' => '<version>/consulta/guardar',

                // sign up
                'POST api/<version:\w+>/signup' => '<version>/signup/registrar',
                //'OPTIONS api/<version:\w+>/signup' => '<version>/signup/recibir',
                // login
                'POST api/<version:\w+>/login' => '<version>/login/login',
                // registro simulado de paciente (Mercedes Diaz) para pruebas móviles
                'POST api/<version:\w+>/registro/simular-paciente-mercedes' => '<version>/registro/simular-paciente-mercedes',
                // login biométrico con Didit
                'POST api/<version:\w+>/auth/login-biometrico' => '<version>/auth/login-biometrico',
                // generar token de prueba para paciente por DNI
                'POST api/<version:\w+>/auth/generar-token-prueba' => '<version>/auth/generar-token-prueba',
                'GET api/<version:\w+>/auth/generar-token-prueba' => '<version>/auth/generar-token-prueba',

                // consulta-chat - chat médico
                'GET api/<version:\w+>/consulta-chat/mensajes/<id:\d+>' => '<version>/consulta-chat/listar-mensajes',
                'OPTIONS api/<version:\w+>/consulta-chat/mensajes/<id:\d+>' => '<version>/consulta-chat/listar-mensajes',
                'POST api/<version:\w+>/consulta-chat/enviar' => '<version>/consulta-chat/enviar',
                'OPTIONS api/<version:\w+>/consulta-chat/enviar' => '<version>/consulta-chat/enviar',
                'POST api/<version:\w+>/consulta-chat/subir' => '<version>/consulta-chat/subir',
                'OPTIONS api/<version:\w+>/consulta-chat/subir' => '<version>/consulta-chat/subir',
                'GET api/<version:\w+>/consulta-chat/estado/<id:\d+>' => '<version>/consulta-chat/estado',
                'OPTIONS api/<version:\w+>/consulta-chat/estado/<id:\d+>' => '<version>/consulta-chat/estado',

                // Motivos de consulta (conversación paciente: texto, audio, fotos)
                'GET api/<version:\w+>/motivos-consulta/mensajes/<id:\d+>' => '<version>/motivos-consulta/listar-mensajes',
                'OPTIONS api/<version:\w+>/motivos-consulta/mensajes/<id:\d+>' => '<version>/motivos-consulta/listar-mensajes',
                'POST api/<version:\w+>/motivos-consulta/enviar' => '<version>/motivos-consulta/enviar',
                'OPTIONS api/<version:\w+>/motivos-consulta/enviar' => '<version>/motivos-consulta/enviar',
                'POST api/<version:\w+>/motivos-consulta/subir' => '<version>/motivos-consulta/subir',
                'OPTIONS api/<version:\w+>/motivos-consulta/subir' => '<version>/motivos-consulta/subir',
                
                // Turnos / agenda API: día operativo; ABM agenda_rrhh = listar|crear|actualizar|eliminar (propio) vs *-para-recurso (staff, listar con id_efector+id_rr_hh)
                'GET api/<version:\w+>/agenda/dia' => '<version>/agenda/dia',
                'OPTIONS api/<version:\w+>/agenda/dia' => '<version>/agenda/dia',
                'GET api/<version:\w+>/agenda/listar-para-recurso' => '<version>/agenda/listar-para-recurso',
                'OPTIONS api/<version:\w+>/agenda/listar-para-recurso' => '<version>/agenda/listar-para-recurso',
                'GET api/<version:\w+>/agenda/listar' => '<version>/agenda/listar',
                'OPTIONS api/<version:\w+>/agenda/listar' => '<version>/agenda/listar',
                'POST api/<version:\w+>/agenda/crear-para-recurso' => '<version>/agenda/crear-para-recurso',
                'OPTIONS api/<version:\w+>/agenda/crear-para-recurso' => '<version>/agenda/crear-para-recurso',
                'POST api/<version:\w+>/agenda/crear' => '<version>/agenda/crear',
                'OPTIONS api/<version:\w+>/agenda/crear' => '<version>/agenda/crear',
                'PUT api/<version:\w+>/agenda/actualizar-para-recurso/<id:\d+>' => '<version>/agenda/actualizar-para-recurso',
                'PATCH api/<version:\w+>/agenda/actualizar-para-recurso/<id:\d+>' => '<version>/agenda/actualizar-para-recurso',
                'OPTIONS api/<version:\w+>/agenda/actualizar-para-recurso/<id:\d+>' => '<version>/agenda/actualizar-para-recurso',
                'PUT api/<version:\w+>/agenda/actualizar/<id:\d+>' => '<version>/agenda/actualizar',
                'PATCH api/<version:\w+>/agenda/actualizar/<id:\d+>' => '<version>/agenda/actualizar',
                'OPTIONS api/<version:\w+>/agenda/actualizar/<id:\d+>' => '<version>/agenda/actualizar',
                'DELETE api/<version:\w+>/agenda/eliminar-para-recurso/<id:\d+>' => '<version>/agenda/eliminar-para-recurso',
                'OPTIONS api/<version:\w+>/agenda/eliminar-para-recurso/<id:\d+>' => '<version>/agenda/eliminar-para-recurso',
                'DELETE api/<version:\w+>/agenda/eliminar/<id:\d+>' => '<version>/agenda/eliminar',
                'OPTIONS api/<version:\w+>/agenda/eliminar/<id:\d+>' => '<version>/agenda/eliminar',
                'GET api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'GET api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'POST api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'GET api/<version:\w+>/turnos/politica-como-paciente' => '<version>/turnos/politica-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/politica-como-paciente' => '<version>/turnos/politica-como-paciente',
                'POST api/<version:\w+>/turnos/cancelar-dia-efector' => '<version>/turnos/cancelar-dia-efector',
                'OPTIONS api/<version:\w+>/turnos/cancelar-dia-efector' => '<version>/turnos/cancelar-dia-efector',
                'GET api/<version:\w+>/turnos/<id:\d+>/slots-alternativos' => '<version>/turnos/slots-alternativos-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/slots-alternativos' => '<version>/turnos/slots-alternativos-como-paciente',
                'POST api/<version:\w+>/turnos/<id:\d+>/cancelar' => '<version>/turnos/cancelar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/cancelar' => '<version>/turnos/cancelar-como-paciente',
                'POST api/<version:\w+>/turnos/<id:\d+>/cancelar-operativo' => '<version>/turnos/cancelar-operativo',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/cancelar-operativo' => '<version>/turnos/cancelar-operativo',
                'POST api/<version:\w+>/turnos/<id:\d+>/no-se-presento' => '<version>/turnos/no-se-presento',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/no-se-presento' => '<version>/turnos/no-se-presento',
                'POST api/<version:\w+>/turnos/<id:\d+>/confirmar-asistencia' => '<version>/turnos/confirmar-asistencia-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/confirmar-asistencia' => '<version>/turnos/confirmar-asistencia-como-paciente',
                'POST api/<version:\w+>/turnos/<id:\d+>/reprogramar' => '<version>/turnos/reprogramar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/<id:\d+>/reprogramar' => '<version>/turnos/reprogramar-como-paciente',
                'GET api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/ver-turno',
                'GET api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'POST api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'OPTIONS api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'GET api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'OPTIONS api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/turnos/para-paciente' => '<version>/turnos/crear-para-paciente',
                'OPTIONS api/<version:\w+>/turnos/para-paciente' => '<version>/turnos/crear-para-paciente',
                'POST api/<version:\w+>/turnos/crear-sobreturno' => '<version>/turnos/crear-sobreturno',
                'OPTIONS api/<version:\w+>/turnos/crear-sobreturno' => '<version>/turnos/crear-sobreturno',
                'POST api/<version:\w+>/turnos' => '<version>/turnos/crear-como-paciente',
                'PUT api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/actualizar-turno',
                'PATCH api/<version:\w+>/turnos/<id:\d+>' => '<version>/turnos/actualizar-turno',
                'POST api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'OPTIONS api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'GET api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/listar',
                'OPTIONS api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/listar',
                'POST api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/crear',
                'OPTIONS api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/crear',
                
                // Personas Timeline API (historia clínica)
                'GET api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'GET api/<version:\w+>/personas/<id:\d+>/timeline' => '<version>/persona/timeline',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/timeline' => '<version>/persona/timeline',

                // Config API
                // (Se eliminan endpoints de listados en ConfigController; ver Efectores/Rrhh/Catálogos/SesiónOperativa)
                
                // Efectores API - Búsqueda
                'GET api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'POST api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'OPTIONS api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'GET api/<version:\w+>/efectores/mis-efectores' => '<version>/efectores/mis-efectores',
                'OPTIONS api/<version:\w+>/efectores/mis-efectores' => '<version>/efectores/mis-efectores',
                
                // Recursos Humanos API - Búsqueda
                'GET api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'POST api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'OPTIONS api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'GET api/<version:\w+>/rrhh/servicios-por-rrhh' => '<version>/rrhh/servicios-por-rrhh',
                'POST api/<version:\w+>/rrhh/servicios-por-rrhh' => '<version>/rrhh/servicios-por-rrhh',
                'OPTIONS api/<version:\w+>/rrhh/servicios-por-rrhh' => '<version>/rrhh/servicios-por-rrhh',

                // Catálogos API
                'GET api/<version:\w+>/catalogos/encounter-classes' => '<version>/catalogos/encounter-classes',
                'OPTIONS api/<version:\w+>/catalogos/encounter-classes' => '<version>/catalogos/encounter-classes',

                // Sesión Operativa API
                'POST api/<version:\w+>/sesion-operativa/establecer' => '<version>/sesion-operativa/establecer',
                'OPTIONS api/<version:\w+>/sesion-operativa/establecer' => '<version>/sesion-operativa/establecer',
                
                // CRUD API
                'GET api/<version:\w+>/crud/ejecutar-accion' => '<version>/crud/ejecutar-accion',
                'POST api/<version:\w+>/crud/ejecutar-accion' => '<version>/crud/ejecutar-accion',
                'OPTIONS api/<version:\w+>/crud/ejecutar-accion' => '<version>/crud/ejecutar-accion',

                // Descriptores de UI dinámica (JSON: wizards, etc.) — mismo action_id que crud/execute-action
                'GET api/<version:\w+>/ui/<entity:[\w-]+>/<action:[\w-]+>' => '<version>/ui/descriptor',
                'OPTIONS api/<version:\w+>/ui/<entity:[\w-]+>/<action:[\w-]+>' => '<version>/ui/options',
                
                // Audio API (Speech-to-Text)
                'POST api/<version:\w+>/audio/transcribir' => '<version>/audio/transcribir',
                'OPTIONS api/<version:\w+>/audio/transcribir' => '<version>/audio/transcribir',

                // Pacientes (encounter resuelto en backend)
                'GET api/<version:\w+>/pacientes' => '<version>/pacientes/listar',
                'OPTIONS api/<version:\w+>/pacientes' => '<version>/pacientes/listar',

                // Quirófano — agenda (salas + cirugías)
                'GET api/<version:\w+>/quirofano/salas' => '<version>/quirofano/listar-salas',
                'POST api/<version:\w+>/quirofano/salas' => '<version>/quirofano/listar-salas',
                'OPTIONS api/<version:\w+>/quirofano/salas' => '<version>/quirofano/options',
                'GET api/<version:\w+>/quirofano/salas/<id:\d+>' => '<version>/quirofano/ver-sala',
                'PATCH api/<version:\w+>/quirofano/salas/<id:\d+>' => '<version>/quirofano/actualizar-sala',
                'DELETE api/<version:\w+>/quirofano/salas/<id:\d+>' => '<version>/quirofano/eliminar-sala',
                'OPTIONS api/<version:\w+>/quirofano/salas/<id:\d+>' => '<version>/quirofano/options',
                'GET api/<version:\w+>/quirofano/cirugias' => '<version>/quirofano/listar-cirugias',
                'POST api/<version:\w+>/quirofano/cirugias' => '<version>/quirofano/listar-cirugias',
                'OPTIONS api/<version:\w+>/quirofano/cirugias' => '<version>/quirofano/options',
                'GET api/<version:\w+>/quirofano/cirugias/<id:\d+>' => '<version>/quirofano/ver-cirugia',
                'PATCH api/<version:\w+>/quirofano/cirugias/<id:\d+>' => '<version>/quirofano/actualizar-cirugia',
                'PATCH api/<version:\w+>/quirofano/cirugias/<id:\d+>/estado' => '<version>/quirofano/estado-cirugia',
                'OPTIONS api/<version:\w+>/quirofano/cirugias/<id:\d+>' => '<version>/quirofano/options',
                'OPTIONS api/<version:\w+>/quirofano/cirugias/<id:\d+>/estado' => '<version>/quirofano/options',
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