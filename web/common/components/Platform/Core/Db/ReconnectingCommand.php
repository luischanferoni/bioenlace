<?php

namespace common\components\Platform\Core\Db;

use yii\db\Command;

/**
 * Reintenta una vez tras reconectar si MySQL cerró la conexión (2006/2013).
 */
class ReconnectingCommand extends Command
{
    public function execute()
    {
        return $this->runWithReconnect(static fn (): int => parent::execute());
    }

    protected function queryInternal($method, $fetchMode = null)
    {
        return $this->runWithReconnect(static fn () => parent::queryInternal($method, $fetchMode));
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function runWithReconnect(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            if (!$this->shouldReconnect($e)) {
                throw $e;
            }
            /** @var ReconnectingConnection $db */
            $db = $this->db;
            $db->reconnect();

            return $fn();
        }
    }

    private function shouldReconnect(\Throwable $e): bool
    {
        if (!$this->db instanceof ReconnectingConnection) {
            return false;
        }
        if ($this->db->getTransaction() !== null) {
            return false;
        }

        return ReconnectingConnection::isGoneAwayException($e);
    }
}
