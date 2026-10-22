<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class TurnoLifecycleNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string}
     */
    public function cancelledByProvider(string $date): array
    {
        return $this->render('cancelled_by_provider', ['date' => $date]);
    }

    /**
     * @return array{title: string, body: string}
     */
    public function overbookDelay(int $minutes): array
    {
        return $this->render('overbook_delay', ['minutes' => (string) $minutes]);
    }

    /**
     * @return array{title: string, body: string}
     */
    public function scheduledOverbookDelay(int $minutes): array
    {
        return $this->render('scheduled_overbook_delay', ['minutes' => (string) $minutes]);
    }

    /**
     * @return array{title: string, body: string, transport_hint: string}
     */
    public function reminder(string $service, string $date, string $time, string $directions): array
    {
        $message = self::load()['messages']['reminder'] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body_template'] ?? null)
            || trim($message['body_template']) === ''
            || !is_string($message['transport_hint'] ?? null)
            || trim($message['transport_hint']) === ''
        ) {
            throw new \RuntimeException('Copy de recordatorio de turno incompleto.');
        }

        $body = str_replace(
            ['{service}', '{date}', '{time}', '{directions}'],
            [$service, $date, $time, $directions],
            $message['body_template']
        );

        return [
            'title' => trim($message['title']),
            'body' => $body,
            'transport_hint' => trim($message['transport_hint']),
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @param array<string, string> $variables
     * @return array{title: string, body: string}
     */
    private function render(string $messageId, array $variables): array
    {
        $message = self::load()['messages'][$messageId] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body_template'] ?? null)
            || trim($message['body_template']) === ''
        ) {
            throw new \RuntimeException('Copy de notificación de turno incompleto: ' . $messageId);
        }

        $placeholders = [];
        $values = [];
        foreach ($variables as $name => $value) {
            $placeholders[] = '{' . $name . '}';
            $values[] = $value;
        }

        return [
            'title' => trim($message['title']),
            'body' => trim(str_replace($placeholders, $values, $message['body_template'])),
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
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'turno-lifecycle-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de ciclo de turno: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo turno-lifecycle-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
