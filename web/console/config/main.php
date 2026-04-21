<?php

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'console\controllers',
    /**
     * webvimark User (common\models\User) usa tableName() vía este módulo.
     * Sin registrarlo, comandos que crean usuarios fallan con "user_table on null".
     */
    'modules' => [
        'user-management' => [
            'class' => \webvimark\modules\UserManagement\UserManagementModule::class,
        ],
    ],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'params' => require __DIR__ . '/../../common/config/params.php',
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => '@common/migrations',
        ],
    ],
];
