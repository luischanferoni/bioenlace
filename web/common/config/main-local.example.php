<?php

/**
 * Copiar a main-local.php y ajustar credenciales.
 *
 * ReconnectingConnection reintenta una vez ante MySQL 2006/2013 (conexión idle o servidor reiniciado).
 * wait_timeout de sesión: params mysqlSessionWaitTimeout (no va en este array).
 *
 * Hostinger: si aparece SQLSTATE[HY000] [2002] Operation not permitted (rate limit de
 * conexiones nuevas), agregar attributes PDO::ATTR_PERSISTENT => true.
 */
return [
    'components' => [
        'db' => [
            'class' => \common\components\Platform\Core\Db\ReconnectingConnection::class,
            'dsn' => 'mysql:host=localhost;dbname=CHANGE_ME;port=3306',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            // 'attributes' => [
            //     \PDO::ATTR_PERSISTENT => true,
            // ],
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
];
