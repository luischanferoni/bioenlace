<?php

namespace common\components\Domain\Clinical\CareCohort\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class CareFollowupTouchpointNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function fallbackTitle(): string
    {
        return $this->requiredString('fallback_title');
    }

    public function bodyForRecipient(bool $isSubject, string $subjectLabel): string
    {
        $key = $isSubject ? 'patient_body' : 'representative_body_template';
        $body = $this->requiredString($key);

        return $isSubject ? $body : str_replace('{subject_label}', $subjectLabel, $body);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    private function requiredString(string $key): string
    {
        $value = self::load()['messages'][$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('Copy incompleto de touchpoint de seguimiento: ' . $key);
        }

        return trim($value);
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
            . DIRECTORY_SEPARATOR . 'CareCohort'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'care-followup-touchpoint-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de touchpoints: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo care-followup-touchpoint-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
