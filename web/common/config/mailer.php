<?php

/**
 * Componente mailer (Symfony Mailer vía yii2-symfonymailer).
 *
 * Sin `mailerDsn` en params-local → useFileTransport (emails en runtime/mail).
 * Producción: 'mailerDsn' => 'smtp://user:pass@host:587' o 'sendmail://default'
 */

$params = require __DIR__ . '/params.php';
$paramsLocal = __DIR__ . '/params-local.php';
if (is_file($paramsLocal)) {
    $params = array_merge($params, require $paramsLocal);
}

$dsn = isset($params['mailerDsn']) ? trim((string) $params['mailerDsn']) : '';

$config = [
    'class' => \yii\symfonymailer\Mailer::class,
    'viewPath' => '@frontend/views/mail',
    'useFileTransport' => $dsn === '',
];

if ($dsn !== '') {
    $config['transport'] = ['dsn' => $dsn];
}

return $config;
