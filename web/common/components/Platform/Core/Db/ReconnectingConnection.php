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

    public function open(): void
    {
        $wasInactive = $this->pdo === null;
        parent::open();
        if ($wasInactive) {
            BioenlaceDb::applySessionWaitTimeout($this);
        }
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
