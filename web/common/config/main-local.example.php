<?php

/**
 * Copiar a main-local.php y ajustar credenciales.
 *
 * ReconnectingConnection reintenta una vez ante MySQL 2006/2013 (conexión idle o servidor reiniciado).
 */
return [
    'components' => [
        'db' => [
            'class' => \common\components\Platform\Core\Db\ReconnectingConnection::class,
            'dsn' => 'mysql:host=localhost;dbname=CHANGE_ME;port=3306',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'sessionWaitTimeout' => 28800,
        ],
        'dbMap' => [
            'class' => \common\components\Platform\Core\Db\ReconnectingConnection::class,
            'dsn' => 'mysql:host=localhost;dbname=CHANGE_ME;port=3306',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'sessionWaitTimeout' => 28800,
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
];
