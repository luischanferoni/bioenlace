<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class PostDischargeFollowupTouchpointCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function title(string $programId, int $touchpointIndex): string
    {
        $titles = self::load()['programs'][$programId] ?? null;
        $title = is_array($titles) ? ($titles[$touchpointIndex] ?? null) : null;
        if (!is_string($title) || trim($title) === '') {
            throw new \RuntimeException(sprintf(
                'Falta el título del touchpoint post-alta: %s/%d.',
                $programId,
                $touchpointIndex
            ));
        }

        return trim($title);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
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
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'Inpatient'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'post-discharge-followup-copy.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de copy post-alta: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['programs'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo post-discharge-followup-copy inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
