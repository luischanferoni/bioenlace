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
        try {
            return parent::execute();
        } catch (\Throwable $e) {
            if (!$this->shouldReconnect($e)) {
                throw $e;
            }
            /** @var ReconnectingConnection $db */
            $db = $this->db;
            $db->reconnect();

            return parent::execute();
        }
    }

    protected function queryInternal($method, $fetchMode = null)
    {
        try {
            return parent::queryInternal($method, $fetchMode);
        } catch (\Throwable $e) {
            if (!$this->shouldReconnect($e)) {
                throw $e;
            }
            /** @var ReconnectingConnection $db */
            $db = $this->db;
            $db->reconnect();

            return parent::queryInternal($method, $fetchMode);
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
