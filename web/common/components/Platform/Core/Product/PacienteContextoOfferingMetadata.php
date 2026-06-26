<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de encauzamiento paciente ({@see ProductMetadataPaths::pacienteContextoOfferingFile()}).
 */
final class PacienteContextoOfferingMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return list<string>
     */
    public static function intentIdsRequiringOperativeContext(): array
    {
        $raw = self::loadConfig()['requires_operative_context']['intent_ids'] ?? [];

        return self::stringList($raw);
    }

    /**
     * @return array{include: list<string>, exclude: list<string>}
     */
    public static function origenFinanciamientoRulesForSector(string $sectorSalud): array
    {
        $sector = strtoupper(trim($sectorSalud));
        $rules = self::loadConfig()['sector_salud'][$sector] ?? [];

        return [
            'include' => self::stringList($rules['origen_financiamiento_include'] ?? []),
            'exclude' => self::stringList($rules['origen_financiamiento_exclude'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    public static function deniedIntentIdsForSector(string $sectorSalud): array
    {
        $sector = strtoupper(trim($sectorSalud));
        $raw = self::loadConfig()['intents_by_sector'][$sector]['deny_intent_ids'] ?? [];

        return self::stringList($raw);
    }

    /**
     * @return list<string>
     */
    public static function homePanelSectionsRequiringOperativeContext(): array
    {
        $raw = self::loadConfig()['home_panel_sections']['patient']['requires_operative_context'] ?? [];

        return self::stringList($raw);
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [];
        $path = ProductMetadataPaths::pacienteContextoOfferingFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('PacienteContextoOfferingMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
