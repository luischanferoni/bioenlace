<?php

namespace common\components\Domain\Clinical\Encounter\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class EncounterPatientSummaryNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function title(): string
    {
        return $this->requiredString('title');
    }

    public function bodyForPatient(): string
    {
        return $this->requiredString('patient_body');
    }

    public function bodyForRepresentative(string $subjectLabel): string
    {
        $template = $this->requiredString('representative_body_template');
        return str_replace('{subject_label}', $subjectLabel, $template);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    private function requiredString(string $key): string
    {
        $value = self::load()['messages']['summary_ready'][$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('Copy incompleto de notificación de resumen de consulta: ' . $key);
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
            . DIRECTORY_SEPARATOR . 'Encounter'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'encounter-patient-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificaciones de consulta: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo encounter-patient-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
