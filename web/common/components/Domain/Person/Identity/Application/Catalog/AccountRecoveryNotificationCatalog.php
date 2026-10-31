<?php

namespace common\components\Domain\Person\Identity\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo de notificaciones para recuperación de cuenta.
 *
 * Copy de notificaciones para:
 * - Usuario: solicitud revisada, aprobada, consumida, rechazada
 * - Operadores: alerta de fraude
 */
final class AccountRecoveryNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string}
     */
    public function reviewed(): array
    {
        return self::getMessage('reviewed');
    }

    /**
     * @return array{title: string, body: string}
     */
    public function approved(): array
    {
        return self::getMessage('approved');
    }

    /**
     * @return array{title: string, body: string}
     */
    public function consumed(): array
    {
        return self::getMessage('consumed');
    }

    /**
     * @return array{title: string, body: string}
     */
    public function rejected(): array
    {
        return self::getMessage('rejected');
    }

    /**
     * @return array{title: string, body: string}
     */
    public function fraudAlert(): array
    {
        return self::getMessage('fraud_alert');
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{title: string, body: string}
     */
    private static function getMessage(string $key): array
    {
        $message = self::load()['notifications'][$key] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body'] ?? null)
            || trim($message['body']) === ''
        ) {
            throw new \RuntimeException("Copy de notificación '$key' incompleto.");
        }

        return [
            'title' => trim($message['title']),
            'body' => trim($message['body']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Person'
            . DIRECTORY_SEPARATOR . 'Identity'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'account-recovery-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de recuperación: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data) || ($data['version'] ?? null) !== 1) {
            throw new \RuntimeException('Catálogo account-recovery-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
