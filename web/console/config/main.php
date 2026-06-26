<?php

use yii\helpers\ArrayHelper;

$paramsLocal = __DIR__ . '/params-local.php';

$params = ArrayHelper::merge(
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
        // AR con blames (created_by) y servicios que leen Yii::$app->user en consola.
        'user' => [
            'class' => \yii\web\User::class,
            'identityClass' => \common\models\User::class,
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
