<?php

use yii\helpers\ArrayHelper;

$paramsLocal = __DIR__ . '/params-local.php';
$commonParamsLocal = __DIR__ . '/../../common/config/params-local.php';

$params = ArrayHelper::merge(
    require __DIR__ . '/../../common/config/params.php',
    is_file($commonParamsLocal) ? require $commonParamsLocal : [],
    require __DIR__ . '/params.php',
    is_file($paramsLocal) ? require $paramsLocal : []
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        \common\components\Platform\Core\Db\EnsureDbConnectionBootstrap::class,
    ],
    'controllerNamespace' => 'console\controllers',
    /**
     * Módulo user-management (UserManagementCompatModule) expone nombres de tabla.
     * Sin registrarlo, comandos que crean usuarios fallan con "user_table on null".
     */
    'modules' => [
        'user-management' => [
            'class' => \common\modules\UserManagementCompatModule::class,
        ],
    ],
    'components' => [
        // Mismo motor RBAC que frontend/admin: sin esto, IntentAccessService /
        // qa/asistente-consultas siempre niegan ejecución (authManager null).
        'authManager' => [
            'class' => 'common\models\Platform\BioenlaceDbManager',
            'efectorAssignmentTable' => 'profesional_efector_servicio',
            'rolesEspeciales' => ['_x_efector_', '_sin_efector_', 'AdminMinisterio'],
        ],
        // AR con blames (created_by) y servicios que leen Yii::$app->user en consola.
        // ConsoleUser expone getIdPersona/getIdEfector… (API web usa ApiUser + sesión).
        'user' => [
            'class' => \console\components\ConsoleUser::class,
            'identityClass' => \common\models\Platform\User::class,
            'enableSession' => false,
            'enableAutoLogin' => false,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'params' => $params,
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => '@common/migrations',
        ],
    ],
];
