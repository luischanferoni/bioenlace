<?php

/**
 * Copiar a main-local.php y ajustar credenciales.
 *
 * ReconnectingConnection reintenta una vez ante MySQL 2006/2013 (conexión idle o servidor reiniciado).
 * wait_timeout de sesión: params mysqlSessionWaitTimeout (no va en este array).
 *
 * Hostinger: si aparece SQLSTATE[HY000] [2002] Operation not permitted (rate limit de
 * conexiones nuevas), agregar attributes PDO::ATTR_PERSISTENT => true.
 *
 * El `mailer` no va acá: lo define common/config/mailer.php (yii2-symfonymailer, DSN en
 * params-local). Sobrescribirlo con swiftmailer rompe el arranque.
 */
return [
    'components' => [
        'db' => [
            'class' => \common\components\Platform\Core\Db\ReconnectingConnection::class,
            'dsn' => 'mysql:host=localhost;dbname=CHANGE_ME;port=3306',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            // 'attributes' => [
            //     \PDO::ATTR_PERSISTENT => true,
            // ],
        ],
    ],
];
