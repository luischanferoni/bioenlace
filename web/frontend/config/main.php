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
            'efectorAssignmentTable' => 'profesional_efector_servicio',
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
                // API clínica FHIR (Encounter, CarePlan, Condition)
                'POST api/<version:\w+>/clinical/encounter/analizar' => '<version>/clinical/encounter/analizar',
                'OPTIONS api/<version:\w+>/clinical/encounter/analizar' => '<version>/clinical/encounter/analizar',
                'POST api/<version:\w+>/clinical/encounter/guardar' => '<version>/clinical/encounter/guardar',
                'OPTIONS api/<version:\w+>/clinical/encounter/guardar' => '<version>/clinical/encounter/guardar',
                'GET api/<version:\w+>/clinical/encounter/listar-ordenes-activas' => '<version>/clinical/encounter/listar-ordenes-activas',
                'OPTIONS api/<version:\w+>/clinical/encounter/listar-ordenes-activas' => '<version>/clinical/encounter/listar-ordenes-activas',
                'GET api/<version:\w+>/clinical/care-plan/ver-tratamiento-paciente' => '<version>/clinical/care-plan/ver-tratamiento-paciente',
                'OPTIONS api/<version:\w+>/clinical/care-plan/ver-tratamiento-paciente' => '<version>/clinical/care-plan/ver-tratamiento-paciente',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/conditions' => '<version>/clinical/condition/index',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/conditions' => '<version>/clinical/condition/index',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/medication-requests' => '<version>/clinical/medication-request/index',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/medication-requests' => '<version>/clinical/medication-request/index',
                'POST api/<version:\w+>/clinical/encounter/<encounterId:\d+>/medication-requests' => '<version>/clinical/medication-request/create',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/service-requests' => '<version>/clinical/service-request/index',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/service-requests' => '<version>/clinical/service-request/index',
                'POST api/<version:\w+>/clinical/encounter/<encounterId:\d+>/service-requests' => '<version>/clinical/service-request/create',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/odontology' => '<version>/clinical/odontology/index',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/odontology' => '<version>/clinical/odontology/index',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/ophthalmology' => '<version>/clinical/ophthalmology/index',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/ophthalmology' => '<version>/clinical/ophthalmology/index',
                'GET api/<version:\w+>/clinical/episode-of-care/by-internacion/<internacionId:\d+>' => '<version>/clinical/episode-of-care/by-internacion',
                'OPTIONS api/<version:\w+>/clinical/episode-of-care/by-internacion/<internacionId:\d+>' => '<version>/clinical/episode-of-care/by-internacion',
                'GET api/<version:\w+>/clinical/episode-of-care/<id:\d+>/clinical-bundle' => '<version>/clinical/episode-of-care/clinical-bundle',
                'OPTIONS api/<version:\w+>/clinical/episode-of-care/<id:\d+>/clinical-bundle' => '<version>/clinical/episode-of-care/clinical-bundle',
                'GET api/<version:\w+>/clinical/care-plans/active' => '<version>/clinical/care-plan/active',
                'OPTIONS api/<version:\w+>/clinical/care-plans/active' => '<version>/clinical/care-plan/active',
                'GET api/<version:\w+>/clinical/care-plans/<id:\d+>' => '<version>/clinical/care-plan/view',
                'OPTIONS api/<version:\w+>/clinical/care-plans/<id:\d+>' => '<version>/clinical/care-plan/view',
                'POST api/<version:\w+>/clinical/care-plans/<id:\d+>/complete' => '<version>/clinical/care-plan/complete',
                'OPTIONS api/<version:\w+>/clinical/care-plans/<id:\d+>/complete' => '<version>/clinical/care-plan/complete',
                'POST api/<version:\w+>/clinical/care-plans/<id:\d+>/revoke' => '<version>/clinical/care-plan/revoke',
                'OPTIONS api/<version:\w+>/clinical/care-plans/<id:\d+>/revoke' => '<version>/clinical/care-plan/revoke',
                'POST api/<version:\w+>/clinical/care-plans/<id:\d+>/hold' => '<version>/clinical/care-plan/hold',
                'OPTIONS api/<version:\w+>/clinical/care-plans/<id:\d+>/hold' => '<version>/clinical/care-plan/hold',
                'POST api/<version:\w+>/clinical/care-plans/<id:\d+>/activate' => '<version>/clinical/care-plan/activate',
                'OPTIONS api/<version:\w+>/clinical/care-plans/<id:\d+>/activate' => '<version>/clinical/care-plan/activate',
                // Legacy plural (laboratory-results) → canónico singular
                'GET api/<version:\w+>/clinical/laboratory-results/<action:[\w\-]+>' => '<version>/clinical/laboratory-result/<action>',
                'POST api/<version:\w+>/clinical/laboratory-results/<action:[\w\-]+>' => '<version>/clinical/laboratory-result/<action>',
                'OPTIONS api/<version:\w+>/clinical/laboratory-results/<action:[\w\-]+>' => '<version>/clinical/laboratory-result/<action>',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/laboratory-results' => '<version>/clinical/laboratory-result/por-encounter',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/laboratory-results' => '<version>/clinical/laboratory-result/por-encounter',
                'GET api/<version:\w+>/clinical/laboratory-result/mis-resultados-como-paciente' => '<version>/clinical/laboratory-result/mis-resultados-como-paciente',
                'POST api/<version:\w+>/clinical/laboratory-result/mis-resultados-como-paciente' => '<version>/clinical/laboratory-result/mis-resultados-como-paciente',
                'OPTIONS api/<version:\w+>/clinical/laboratory-result/mis-resultados-como-paciente' => '<version>/clinical/laboratory-result/mis-resultados-como-paciente',
                'GET api/<version:\w+>/clinical/laboratory-result/ver-informe-como-paciente' => '<version>/clinical/laboratory-result/ver-informe-como-paciente',
                'POST api/<version:\w+>/clinical/laboratory-result/ver-informe-como-paciente' => '<version>/clinical/laboratory-result/ver-informe-como-paciente',
                'OPTIONS api/<version:\w+>/clinical/laboratory-result/ver-informe-como-paciente' => '<version>/clinical/laboratory-result/ver-informe-como-paciente',
                'GET api/<version:\w+>/clinical/laboratory-result/descargar-pdf-como-paciente' => '<version>/clinical/laboratory-result/descargar-pdf-como-paciente',
                'OPTIONS api/<version:\w+>/clinical/laboratory-result/descargar-pdf-como-paciente' => '<version>/clinical/laboratory-result/descargar-pdf-como-paciente',
                'GET api/<version:\w+>/clinical/encounter/<encounterId:\d+>/laboratory-result' => '<version>/clinical/laboratory-result/por-encounter',
                'OPTIONS api/<version:\w+>/clinical/encounter/<encounterId:\d+>/laboratory-result' => '<version>/clinical/laboratory-result/por-encounter',
                // Legacy consulta (410 Gone — usar clinical/encounter/*)
                'POST api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
                'OPTIONS api/<version:\w+>/consulta/analizar' => '<version>/consulta/analizar',
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
                
                // Turnos / agenda profesional API: día operativo; ABM profesional_efector_servicio_agenda = listar|crear|actualizar|eliminar (propio) vs *-para-recurso (staff; query id_efector + id_profesional_efector_servicio)
                'GET api/<version:\w+>/profesional-agenda/dia' => '<version>/profesional-agenda/dia',
                'OPTIONS api/<version:\w+>/profesional-agenda/dia' => '<version>/profesional-agenda/dia',
                'GET api/<version:\w+>/profesional-agenda/listar-para-recurso' => '<version>/profesional-agenda/listar-para-recurso',
                'OPTIONS api/<version:\w+>/profesional-agenda/listar-para-recurso' => '<version>/profesional-agenda/listar-para-recurso',
                'GET api/<version:\w+>/profesional-agenda/listar' => '<version>/profesional-agenda/listar',
                'OPTIONS api/<version:\w+>/profesional-agenda/listar' => '<version>/profesional-agenda/listar',
                'POST api/<version:\w+>/profesional-agenda/crear-para-recurso' => '<version>/profesional-agenda/crear-para-recurso',
                'OPTIONS api/<version:\w+>/profesional-agenda/crear-para-recurso' => '<version>/profesional-agenda/crear-para-recurso',
                'POST api/<version:\w+>/profesional-agenda/crear' => '<version>/profesional-agenda/crear',
                'OPTIONS api/<version:\w+>/profesional-agenda/crear' => '<version>/profesional-agenda/crear',
                'PUT api/<version:\w+>/profesional-agenda/actualizar-para-recurso/<id:\d+>' => '<version>/profesional-agenda/actualizar-para-recurso',
                'PATCH api/<version:\w+>/profesional-agenda/actualizar-para-recurso/<id:\d+>' => '<version>/profesional-agenda/actualizar-para-recurso',
                'OPTIONS api/<version:\w+>/profesional-agenda/actualizar-para-recurso/<id:\d+>' => '<version>/profesional-agenda/actualizar-para-recurso',
                'PUT api/<version:\w+>/profesional-agenda/actualizar/<id:\d+>' => '<version>/profesional-agenda/actualizar',
                'PATCH api/<version:\w+>/profesional-agenda/actualizar/<id:\d+>' => '<version>/profesional-agenda/actualizar',
                'OPTIONS api/<version:\w+>/profesional-agenda/actualizar/<id:\d+>' => '<version>/profesional-agenda/actualizar',
                'DELETE api/<version:\w+>/profesional-agenda/eliminar-para-recurso/<id:\d+>' => '<version>/profesional-agenda/eliminar-para-recurso',
                'OPTIONS api/<version:\w+>/profesional-agenda/eliminar-para-recurso/<id:\d+>' => '<version>/profesional-agenda/eliminar-para-recurso',
                'DELETE api/<version:\w+>/profesional-agenda/eliminar/<id:\d+>' => '<version>/profesional-agenda/eliminar',
                'OPTIONS api/<version:\w+>/profesional-agenda/eliminar/<id:\d+>' => '<version>/profesional-agenda/eliminar',
                'GET api/<version:\w+>/profesional-agenda/configurar-agenda' => '<version>/profesional-agenda/configurar-agenda',
                'POST api/<version:\w+>/profesional-agenda/configurar-agenda' => '<version>/profesional-agenda/configurar-agenda',
                'OPTIONS api/<version:\w+>/profesional-agenda/configurar-agenda' => '<version>/profesional-agenda/configurar-agenda',
                'POST api/<version:\w+>/profesional-agenda/preview-configurar-agenda' => '<version>/profesional-agenda/preview-configurar-agenda',
                'OPTIONS api/<version:\w+>/profesional-agenda/preview-configurar-agenda' => '<version>/profesional-agenda/preview-configurar-agenda',
                'GET api/<version:\w+>/profesional-agenda/crear-agenda-flow' => '<version>/profesional-agenda/crear-agenda-flow',
                'POST api/<version:\w+>/profesional-agenda/crear-agenda-flow' => '<version>/profesional-agenda/crear-agenda-flow',
                'OPTIONS api/<version:\w+>/profesional-agenda/crear-agenda-flow' => '<version>/profesional-agenda/crear-agenda-flow',
                'GET api/<version:\w+>/profesional-agenda/editar-agenda-flow' => '<version>/profesional-agenda/editar-agenda-flow',
                'POST api/<version:\w+>/profesional-agenda/editar-agenda-flow' => '<version>/profesional-agenda/editar-agenda-flow',
                'OPTIONS api/<version:\w+>/profesional-agenda/editar-agenda-flow' => '<version>/profesional-agenda/editar-agenda-flow',
                'GET api/<version:\w+>/profesional-agenda/elegir-conflicto-agenda' => '<version>/profesional-agenda/elegir-conflicto-agenda',
                'POST api/<version:\w+>/profesional-agenda/elegir-conflicto-agenda' => '<version>/profesional-agenda/elegir-conflicto-agenda',
                'OPTIONS api/<version:\w+>/profesional-agenda/elegir-conflicto-agenda' => '<version>/profesional-agenda/elegir-conflicto-agenda',
                'GET api/<version:\w+>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente' => '<version>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente',
                'POST api/<version:\w+>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente' => '<version>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente',
                'OPTIONS api/<version:\w+>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente' => '<version>/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente',
                'POST api/<version:\w+>/profesional-agenda/resolver-conflicto-agenda-para-paciente' => '<version>/profesional-agenda/resolver-conflicto-agenda-para-paciente',
                'OPTIONS api/<version:\w+>/profesional-agenda/resolver-conflicto-agenda-para-paciente' => '<version>/profesional-agenda/resolver-conflicto-agenda-para-paciente',
                'GET api/<version:\w+>/profesional-agenda/ver-agenda-dia' => '<version>/profesional-agenda/ver-agenda-dia',
                'POST api/<version:\w+>/profesional-agenda/ver-agenda-dia' => '<version>/profesional-agenda/ver-agenda-dia',
                'OPTIONS api/<version:\w+>/profesional-agenda/ver-agenda-dia' => '<version>/profesional-agenda/ver-agenda-dia',
                'GET api/<version:\w+>/profesional-efector-servicio/editar-condicion-laboral' => '<version>/profesional-efector-servicio/editar-condicion-laboral',
                'POST api/<version:\w+>/profesional-efector-servicio/editar-condicion-laboral' => '<version>/profesional-efector-servicio/editar-condicion-laboral',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/editar-condicion-laboral' => '<version>/profesional-efector-servicio/editar-condicion-laboral',
                'GET api/<version:\w+>/profesional-efector-servicio/crear-condicion-laboral' => '<version>/profesional-efector-servicio/crear-condicion-laboral',
                'POST api/<version:\w+>/profesional-efector-servicio/crear-condicion-laboral' => '<version>/profesional-efector-servicio/crear-condicion-laboral',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/crear-condicion-laboral' => '<version>/profesional-efector-servicio/crear-condicion-laboral',
                
                // Turnos (rutas de compat / consumo histórico): algunos clientes consumen /api/<v>/turnos/*
                // Nota: los descriptores JSON viven en `modules/api/v1/views/json/...` pero se exponen como `/api/<v>/turnos/*`.
                'GET api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'POST api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/listar-como-paciente' => '<version>/turnos/listar-como-paciente',
                'GET api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'POST api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-disponibles-como-paciente' => '<version>/turnos/slots-disponibles-como-paciente',
                'GET api/<version:\w+>/turnos/slots-dias-disponibles-como-paciente' => '<version>/turnos/slots-dias-disponibles-como-paciente',
                'POST api/<version:\w+>/turnos/slots-dias-disponibles-como-paciente' => '<version>/turnos/slots-dias-disponibles-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-dias-disponibles-como-paciente' => '<version>/turnos/slots-dias-disponibles-como-paciente',
                'GET api/<version:\w+>/turnos/elegir-pendiente-como-paciente' => '<version>/turnos/elegir-pendiente-como-paciente',
                'POST api/<version:\w+>/turnos/elegir-pendiente-como-paciente' => '<version>/turnos/elegir-pendiente-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/elegir-pendiente-como-paciente' => '<version>/turnos/elegir-pendiente-como-paciente',
                'GET api/<version:\w+>/turnos/elegir-motivo-cancelacion-como-paciente' => '<version>/turnos/elegir-motivo-cancelacion-como-paciente',
                'POST api/<version:\w+>/turnos/elegir-motivo-cancelacion-como-paciente' => '<version>/turnos/elegir-motivo-cancelacion-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/elegir-motivo-cancelacion-como-paciente' => '<version>/turnos/elegir-motivo-cancelacion-como-paciente',
                'GET api/<version:\w+>/turnos/slots-reprogramar-como-paciente' => '<version>/turnos/slots-reprogramar-como-paciente',
                'POST api/<version:\w+>/turnos/slots-reprogramar-como-paciente' => '<version>/turnos/slots-reprogramar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-reprogramar-como-paciente' => '<version>/turnos/slots-reprogramar-como-paciente',
                'GET api/<version:\w+>/turnos/elegir-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-conflicto-agenda-como-paciente',
                'POST api/<version:\w+>/turnos/elegir-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-conflicto-agenda-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/elegir-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-conflicto-agenda-como-paciente',
                'GET api/<version:\w+>/turnos/elegir-resolucion-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-resolucion-conflicto-agenda-como-paciente',
                'POST api/<version:\w+>/turnos/elegir-resolucion-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-resolucion-conflicto-agenda-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/elegir-resolucion-conflicto-agenda-como-paciente' => '<version>/turnos/elegir-resolucion-conflicto-agenda-como-paciente',
                'POST api/<version:\w+>/turnos/resolver-conflicto-agenda-como-paciente' => '<version>/turnos/resolver-conflicto-agenda-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/resolver-conflicto-agenda-como-paciente' => '<version>/turnos/resolver-conflicto-agenda-como-paciente',
                'GET api/<version:\w+>/turnos/elegir-en-resolucion-como-paciente' => '<version>/turnos/elegir-en-resolucion-como-paciente',
                'POST api/<version:\w+>/turnos/elegir-en-resolucion-como-paciente' => '<version>/turnos/elegir-en-resolucion-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/elegir-en-resolucion-como-paciente' => '<version>/turnos/elegir-en-resolucion-como-paciente',
                'GET api/<version:\w+>/turnos/slots-reubicar-como-paciente' => '<version>/turnos/slots-reubicar-como-paciente',
                'POST api/<version:\w+>/turnos/slots-reubicar-como-paciente' => '<version>/turnos/slots-reubicar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/slots-reubicar-como-paciente' => '<version>/turnos/slots-reubicar-como-paciente',
                'POST api/<version:\w+>/turnos/reubicar-como-paciente' => '<version>/turnos/reubicar-como-paciente',
                'OPTIONS api/<version:\w+>/turnos/reubicar-como-paciente' => '<version>/turnos/reubicar-como-paciente',
                'GET api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'POST api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'OPTIONS api/<version:\w+>/turnos/eventos' => '<version>/turnos/consultar-ocupacion-dia',
                'GET api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'OPTIONS api/<version:\w+>/turnos/proximo-disponible' => '<version>/turnos/consultar-proximo-disponible',
                'POST api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'OPTIONS api/<version:\w+>/devices/push-token' => '<version>/device/push-token',
                'GET api/<version:\w+>/notificaciones/listar-como-paciente' => '<version>/notificaciones/listar-como-paciente',
                'POST api/<version:\w+>/notificaciones/listar-como-paciente' => '<version>/notificaciones/listar-como-paciente',
                'OPTIONS api/<version:\w+>/notificaciones/listar-como-paciente' => '<version>/notificaciones/listar-como-paciente',
                'POST api/<version:\w+>/notificaciones/marcar-leida-como-paciente' => '<version>/notificaciones/marcar-leida-como-paciente',
                'OPTIONS api/<version:\w+>/notificaciones/marcar-leida-como-paciente' => '<version>/notificaciones/marcar-leida-como-paciente',
                'GET api/<version:\w+>/solicitud-profesional' => '<version>/solicitud-profesional/listar',
                'OPTIONS api/<version:\w+>/solicitud-profesional' => '<version>/solicitud-profesional/listar',
                'POST api/<version:\w+>/solicitud-profesional' => '<version>/solicitud-profesional/crear',
                
                // Personas Timeline API (historia clínica)
                'GET api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/signos-vitales' => '<version>/persona/signos-vitales',
                'GET api/<version:\w+>/personas/<id:\d+>/historia-clinica' => '<version>/pacientes/historia-clinica',
                'OPTIONS api/<version:\w+>/personas/<id:\d+>/historia-clinica' => '<version>/pacientes/historia-clinica',

                // Config API
                // (Se eliminan endpoints de listados en ConfigController; ver Efectores/PES/Catálogos/SesiónOperativa)
                
                // Efectores API - Búsqueda
                'GET api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'POST api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'OPTIONS api/<version:\w+>/efectores/buscar' => '<version>/efectores/buscar',
                'GET api/<version:\w+>/efectores/listar-servicios-habilitados' => '<version>/efectores/listar-servicios-habilitados',
                'POST api/<version:\w+>/efectores/listar-servicios-habilitados' => '<version>/efectores/listar-servicios-habilitados',
                'OPTIONS api/<version:\w+>/efectores/listar-servicios-habilitados' => '<version>/efectores/listar-servicios-habilitados',

                // Profesional efector servicio (PES) API - Búsqueda / listados UI
                'GET api/<version:\w+>/profesional-efector-servicio/autocomplete' => '<version>/profesional-efector-servicio/autocomplete',
                'POST api/<version:\w+>/profesional-efector-servicio/autocomplete' => '<version>/profesional-efector-servicio/autocomplete',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/autocomplete' => '<version>/profesional-efector-servicio/autocomplete',
                'GET api/<version:\w+>/profesional-efector-servicio/listar-mis-servicios-en-efector' => '<version>/profesional-efector-servicio/listar-mis-servicios-en-efector',
                'POST api/<version:\w+>/profesional-efector-servicio/listar-mis-servicios-en-efector' => '<version>/profesional-efector-servicio/listar-mis-servicios-en-efector',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/listar-mis-servicios-en-efector' => '<version>/profesional-efector-servicio/listar-mis-servicios-en-efector',
                'GET api/<version:\w+>/profesional-efector-servicio/listar-por-efector' => '<version>/profesional-efector-servicio/listar-por-efector',
                'POST api/<version:\w+>/profesional-efector-servicio/listar-por-efector' => '<version>/profesional-efector-servicio/listar-por-efector',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/listar-por-efector' => '<version>/profesional-efector-servicio/listar-por-efector',
                'GET api/<version:\w+>/profesional-efector-servicio/condiciones-laborales-catalogo' => '<version>/profesional-efector-servicio/condiciones-laborales-catalogo',
                'OPTIONS api/<version:\w+>/profesional-efector-servicio/condiciones-laborales-catalogo' => '<version>/profesional-efector-servicio/condiciones-laborales-catalogo',

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
        'forms' => [
            'class' => 'frontend\components\apis\Forms',
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