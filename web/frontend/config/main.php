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

                // Preflight CORS genérico (evita declarar OPTIONS por endpoint)
                'OPTIONS api/<version:\w+>/<controller:[\\w-]+>' => '<version>/<controller>/options',
                'OPTIONS api/<version:\w+>/<controller:[\\w-]+>/<action:[\\w-]+>' => '<version>/<controller>/options',
                // analisis de la consulta
                'POST api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                'OPTIONS api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                // guardar consulta completa
                'POST api/<version:\w+>/consulta/guardar' => '<version>/consulta/guardar',
                'OPTIONS api/<version:\w+>/consulta/guardar' => '<version>/consulta/guardar',

                // sign up
                'POST api/<version:\w+>/signup' => '<version>/signup/registrar',
                // OPTIONS: cubierto por behaviors del controller (CORS/preflight) si aplica
                // login
                'POST api/<version:\w+>/login' => '<version>/login/login',
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
                
                // Turnos (rutas de compat / consumo histórico): algunos clientes consumen /api/<v>/turnos/*
                // Nota: los descriptores JSON viven en `modules/api/v1/views/json/...` pero se exponen como `/api/<v>/turnos/*`.
                'GET api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'POST api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'GET api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'POST api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'GET api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'POST api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'OPTIONS api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'GET api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'OPTIONS api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'OPTIONS api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'GET api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/listar',
                'OPTIONS api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/listar',
                'POST api/<version:\w+>/solicitud-rrhh' => '<version>/solicitud-rrhh/crear',
                
                // Personas Timeline API (historia clínica)
                'GET api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'GET api/<version:\w+>/personas/<id:\d+>/historia-clinica' => '<version>/pacientes/historia-clinica',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/historia-clinica' => '<version>/pacientes/historia-clinica',

                // Config API
                // (Se eliminan endpoints de listados en ConfigController; ver Efectores/Rrhh/Catálogos/SesiónOperativa)
                
                // Efectores API - Búsqueda
                'GET api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'POST api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'OPTIONS api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                
                // Recursos Humanos API - Búsqueda
                'GET api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'POST api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'OPTIONS api/<version:\w+>/rrhh/autocomplete' => '<version>/rrhh/autocomplete',
                'GET api/<version:\w+>/rrhh/listar-servicios-en-efector' => '<version>/rrhh/listar-servicios-en-efector',
                'POST api/<version:\w+>/rrhh/listar-servicios-en-efector' => '<version>/rrhh/listar-servicios-en-efector',
                'OPTIONS api/<version:\w+>/rrhh/listar-servicios-en-efector' => '<version>/rrhh/listar-servicios-en-efector',
                'GET api/<version:\w+>/rrhh/listar-por-efector' => '<version>/rrhh/listar-por-efector',
                'POST api/<version:\w+>/rrhh/listar-por-efector' => '<version>/rrhh/listar-por-efector',
                'OPTIONS api/<version:\w+>/rrhh/listar-por-efector' => '<version>/rrhh/listar-por-efector',
                'GET api/<version:\w+>/rrhh/condiciones-laborales-catalogo' => '<version>/rrhh/condiciones-laborales-catalogo',
                'OPTIONS api/<version:\w+>/rrhh/condiciones-laborales-catalogo' => '<version>/rrhh/condiciones-laborales-catalogo',

                // Catálogos API
                'GET api/<version:\w+>/catalogos/encounter-classes' => '<version>/catalogos/encounter-classes',
                'OPTIONS api/<version:\w+>/catalogos/encounter-classes' => '<version>/catalogos/encounter-classes',

                // Sesión Operativa API
                'POST api/<version:\w+>/sesion-operativa/establecer' => '<version>/sesion-operativa/establecer',
                'OPTIONS api/<version:\w+>/sesion-operativa/establecer' => '<version>/sesion-operativa/establecer',

                // Acciones comunes (web SPA / móvil; filtrado por permisos en servicio)
                'GET api/<version:\w+>/acciones/comunes' => '<version>/acciones/comunes',
                'OPTIONS api/<version:\w+>/acciones/comunes' => '<version>/acciones/comunes',
                
                // Descriptores JSON (plantillas en `frontend/modules/api/v1/views/json/{entidad}/{accion}.json`)
                // se exponen como endpoints normales: `/api/v1/<entidad>/<accion>`.
                //
                // Importante: las reglas explícitas de API (arriba) tienen prioridad; esta regla “catch-all”
                // solo aplica a rutas no declaradas explícitamente.
                'GET api/<version:\w+>/<controller:[\\w-]+>/<action:[\\w-]+>' => '<version>/<controller>/<action>',
                'POST api/<version:\w+>/<controller:[\\w-]+>/<action:[\\w-]+>' => '<version>/<controller>/<action>',
                
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