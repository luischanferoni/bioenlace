<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo de preguntas previas al chat de motivos ({@see metadata/motivos_consulta_intake.yaml}).
 */
final class EncounterMotivosIntakeCatalogService
{
    private const CATALOG_FILE = 'motivos_consulta_intake.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function isEnabled(): bool
    {
        if (!((bool) (self::load()['enabled'] ?? false))) {
            return false;
        }

        return $this->questions() !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        $raw = self::load()['questions'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function title(): string
    {
        $title = trim((string) (self::load()['title'] ?? ''));

        return $title !== '' ? $title : 'Preguntas previas';
    }

    public function intro(): string
    {
        return trim((string) (self::load()['intro'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function packContent(): array
    {
        return [
            'questions' => $this->questions(),
            'notes_for_staff' => trim((string) (self::load()['notes_for_staff'] ?? '')),
        ];
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
        $path = dirname(__DIR__, 2) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = ['version' => 1, 'enabled' => false, 'questions' => []];

            return self::$cache;
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo motivos_consulta_intake inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
