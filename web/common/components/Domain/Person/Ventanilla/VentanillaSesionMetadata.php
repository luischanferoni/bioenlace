<?php

namespace common\components\Domain\Person\Ventanilla;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

final class VentanillaSesionMetadata
{
    private const DEFAULT_TTL_MINUTES = 15;

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function reset(): void
    {
        self::$config = null;
    }

    public static function ttlMinutes(): int
    {
        $raw = self::loadConfig()['ttl_minutes'] ?? self::DEFAULT_TTL_MINUTES;
        $ttl = (int) $raw;
        if ($ttl < 1) {
            return self::DEFAULT_TTL_MINUTES;
        }
        if ($ttl > 120) {
            return 120;
        }

        return $ttl;
    }

    /**
     * @return list<string>
     */
    public static function unhidePacienteIntentIds(): array
    {
        $raw = self::loadConfig()['unhide_paciente_intent_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ProductMetadataPaths::ventanillaSesionFile();
        if (!is_file($path)) {
            self::$config = [];

            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('ventanilla-sesion.yaml: ' . $e->getMessage(), __METHOD__);
            self::$config = [];

            return self::$config;
        }

        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }
}
