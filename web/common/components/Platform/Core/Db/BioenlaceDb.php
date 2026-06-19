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

    public static function ensureConnection(): void
    {
        if (!Yii::$app->has('db')) {
            return;
        }
        $db = Yii::$app->db;
        if ($db->pdo === null) {
            $db->open();

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
