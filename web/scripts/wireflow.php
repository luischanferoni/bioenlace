<?php

/**
 * Imprime el wireflow que la guía adjunta para un intent.
 *
 * Uso, desde web/:
 *   php scripts/wireflow.php atencion.necesito-atencion
 */

$intentId = '';
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '-h' || $arg === '--help') {
        fwrite(STDOUT, "Uso: php scripts/wireflow.php <intent_id>\n");
        exit(0);
    }
    if ($intentId === '' && $arg !== '' && strncmp($arg, '-', 1) !== 0) {
        $intentId = $arg;
    }
}

if ($intentId === '') {
    fwrite(STDERR, "Falta el intent. Uso: php scripts/wireflow.php <intent_id>\n");
    exit(1);
}

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

$web = dirname(__DIR__);
require_once $web . '/vendor/autoload.php';
require_once $web . '/vendor/yiisoft/yii2/Yii.php';
require $web . '/common/config/bootstrap.php';

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;

$path = IntentSchemaPaths::resolveFileForIntentId($intentId);
if ($path === null || !is_file($path)) {
    fwrite(STDERR, 'No hay un intent «' . $intentId . "».\n");
    exit(1);
}

$wireflow = IntentSemanticsPromptFormatter::formatIntentId($intentId);
if ($wireflow === '') {
    fwrite(STDERR, 'No se pudo armar el wireflow de «' . $intentId . "».\n");
    exit(1);
}

fwrite(STDOUT, $wireflow . "\n");
