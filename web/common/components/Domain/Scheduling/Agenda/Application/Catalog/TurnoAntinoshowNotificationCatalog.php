<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de notificaciones al paciente para el agente anti no-show.
 */
final class TurnoAntinoshowNotificationCatalog
{
    /** @var array<string, array{title: string, body: string}>|null */
    private static ?array $messages = null;

    /**
     * @return array{title: string, body: string}
     */
    public function forMessage(string $messageId): array
    {
        $message = self::load()[$messageId] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body'] ?? null)
            || trim($message['body']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de mensaje anti no-show: ' . $messageId . '.');
        }

        return [
            'title' => trim($message['title']),
            'body' => trim($message['body']),
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$messages = null;
    }

    /**
     * @return array<string, array{title: string, body: string}>
     */
    private static function load(): array
    {
        if (self::$messages !== null) {
            return self::$messages;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-antinoshow-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones anti no-show: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-antinoshow-notifications inválido.');
        }

        foreach (['extra_confirm', 'scheduled_confirm_request', 'reminder', 'slot_released'] as $messageId) {
            $message = $data['messages'][$messageId] ?? null;
            if (
                !is_array($message)
                || !is_string($message['title'] ?? null)
                || trim($message['title']) === ''
                || !is_string($message['body'] ?? null)
                || trim($message['body']) === ''
            ) {
                throw new \RuntimeException('Definición incompleta de mensaje anti no-show: ' . $messageId . '.');
            }
        }

        self::$messages = $data['messages'];

        return self::$messages;
    }
}
