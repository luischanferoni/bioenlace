<?php

namespace common\components\Platform\Core\Db;

use Yii;
use yii\db\Exception as DbException;

/**
 * Reconexión MySQL tras operaciones largas (p. ej. llamadas HTTP a IA) que dejan la conexión idle.
 */
final class BioenlaceDb
{
    public static function releaseConnection(): void
    {
        if (!Yii::$app->has('db')) {
            return;
        }
        $db = Yii::$app->db;
        if ($db->pdo !== null) {
            $db->close();
        }
    }

    public static function ensureAllConnections(): void
    {
        self::ensureConnection('db');
        if (Yii::$app->has('dbMap')) {
            self::ensureConnection('dbMap');
        }
    }

    public static function ensureConnection(string $componentId = 'db'): void
    {
        if (!Yii::$app->has($componentId)) {
            return;
        }
        $db = Yii::$app->get($componentId);
        if ($db->pdo === null) {
            $db->open();
            self::applySessionWaitTimeout($db);

            return;
        }
        try {
            $db->createCommand('SELECT 1')->execute();
        } catch (\Throwable $e) {
            if (!self::isConnectionLost($e)) {
                throw $e;
            }
            $db->close();
            $db->open();
            self::applySessionWaitTimeout($db);
        }
    }

    /**
     * @param object $db yii\db\Connection
     */
    public static function applySessionWaitTimeout(object $db): void
    {
        if (!isset($db->pdo) || $db->pdo === null) {
            return;
        }
        if (!method_exists($db, 'getDriverName') || $db->getDriverName() !== 'mysql') {
            return;
        }
        $timeout = Yii::$app->params['mysqlSessionWaitTimeout'] ?? null;
        if ($timeout === null) {
            return;
        }
        $timeout = (int) $timeout;
        if ($timeout <= 0) {
            return;
        }
        try {
            $db->pdo->exec('SET SESSION wait_timeout = ' . $timeout);
        } catch (\Throwable $e) {
            Yii::warning(
                'No se pudo fijar mysqlSessionWaitTimeout: ' . $e->getMessage(),
                __METHOD__
            );
        }
    }

    public static function isConnectionLost(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '2006') || str_contains($msg, '2013')) {
            return true;
        }
        if (stripos($msg, 'server has gone away') !== false) {
            return true;
        }
        if (stripos($msg, 'Lost connection') !== false) {
            return true;
        }
        if ($e instanceof DbException && isset($e->errorInfo[1])) {
            $code = (int) $e->errorInfo[1];

            return $code === 2006 || $code === 2013;
        }

        return false;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function withReconnectOnLost(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            if (!self::isConnectionLost($e)) {
                throw $e;
            }
            self::ensureConnection();

            return $fn();
        }
    }
}
