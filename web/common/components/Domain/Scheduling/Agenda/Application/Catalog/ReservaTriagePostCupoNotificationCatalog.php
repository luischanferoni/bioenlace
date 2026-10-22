<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy del push que acompaña recomendaciones de ruteo post-cupo.
 */
final class ReservaTriagePostCupoNotificationCatalog
{
    /** @var array{title: string, body_template: string}|null */
    private static ?array $push = null;

    /**
     * @return array{title: string, body_template: string}
     */
    public function push(): array
    {
        if (self::$push !== null) {
            return self::$push;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'reserva-triage-post-cupo-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones post-cupo: ' . $path);
        }

        $data = Yaml::parseFile($path);
        $push = is_array($data) && ($data['version'] ?? null) === 1
            ? ($data['push'] ?? null)
            : null;
        if (
            !is_array($push)
            || !is_string($push['title'] ?? null)
            || trim($push['title']) === ''
            || !is_string($push['body_template'] ?? null)
            || trim($push['body_template']) === ''
        ) {
            throw new \RuntimeException('Definición incompleta de push para ruteo post-cupo.');
        }

        self::$push = [
            'title' => trim($push['title']),
            'body_template' => trim($push['body_template']),
        ];

        return self::$push;
    }

    public static function resetCacheForTests(): void
    {
        self::$push = null;
    }
}
