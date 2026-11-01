<?php

namespace common\components\Platform\Core\Auth;

use RuntimeException;
use Yii;
use yii\helpers\FileHelper;

final class ImpersonationTicketService
{
    private const TICKET_ALIAS = '@sharedRuntime/impersonation/a.txt';

    public static function store(int $userId): void
    {
        $path = Yii::getAlias(self::TICKET_ALIAS);
        FileHelper::createDirectory(dirname($path));

        if (file_put_contents($path, (string) $userId, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el ticket de impersonación.');
        }
    }

    public static function read(): ?string
    {
        $path = Yii::getAlias(self::TICKET_ALIAS);
        if (!is_file($path)) {
            return null;
        }

        $ticket = file_get_contents($path);
        if ($ticket === false) {
            throw new RuntimeException('No se pudo leer el ticket de impersonación.');
        }

        return $ticket;
    }

    public static function hasPending(): bool
    {
        $ticket = self::read();

        return $ticket !== null && trim($ticket) !== '';
    }

    public static function clear(): void
    {
        $path = Yii::getAlias(self::TICKET_ALIAS);
        if (is_file($path) && file_put_contents($path, '', LOCK_EX) === false) {
            throw new RuntimeException('No se pudo limpiar el ticket de impersonación.');
        }
    }
}
