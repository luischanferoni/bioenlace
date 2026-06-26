<?php

namespace common\components\Platform\Core\Db;

use yii\db\Connection;

/**
 * Conexión MySQL con reintento ante "server has gone away" (2006) o conexión perdida (2013).
 *
 * @see ReconnectingCommand
 */
class ReconnectingConnection extends Connection
{
    /** @var class-string */
    public $commandClass = ReconnectingCommand::class;

    /**
     * Segundos para SET SESSION wait_timeout al abrir (null = no tocar).
     * En shared hosting ayuda a alinear con el límite del servidor.
     */
    public ?int $sessionWaitTimeout = 28800;

    public function open(): void
    {
        $wasInactive = $this->pdo === null;
        parent::open();
        if (!$wasInactive || $this->sessionWaitTimeout === null || $this->pdo === null) {
            return;
        }
        if ($this->getDriverName() !== 'mysql') {
            return;
        }
        $timeout = (int) $this->sessionWaitTimeout;
        if ($timeout <= 0) {
            return;
        }
        $this->pdo->exec('SET SESSION wait_timeout = ' . $timeout);
    }

    public function reconnect(): void
    {
        $this->close();
        $this->open();
    }

    public static function isGoneAwayException(\Throwable $e): bool
    {
        return BioenlaceDb::isConnectionLost($e);
    }
}
