<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy de las alertas SLA de la bandeja async para el staff.
 */
final class EncounterAsyncInboxPriorityNotificationCatalog
{
    /** @var array{title: string, body: string}|null */
    private static ?array $slaEscalationPush = null;

    /**
     * @return array{title: string, body: string}
     */
    public function slaEscalationPush(): array
    {
        if (self::$slaEscalationPush !== null) {
            return self::$slaEscalationPush;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'encounter-async-inbox-priority-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de prioridad async: ' . $path);
        }

        $data = Yaml::parseFile($path);
        $push = is_array($data) && ($data['version'] ?? null) === 1
            ? ($data['sla_escalation_push'] ?? null)
            : null;
        if (
            !is_array($push)
            || !is_string($push['title'] ?? null)
            || trim($push['title']) === ''
            || !is_string($push['body'] ?? null)
            || trim($push['body']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de notificación SLA para la bandeja async.');
        }

        self::$slaEscalationPush = [
            'title' => trim($push['title']),
            'body' => trim($push['body']),
        ];

        return self::$slaEscalationPush;
    }

    public static function resetCacheForTests(): void
    {
        self::$slaEscalationPush = null;
    }
}
