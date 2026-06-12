<?php

namespace common\components\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Carga {@see schemas/intent-aliases.yaml}.
 */
final class IntentAliasCatalog
{
    /** @var array<string, string>|null */
    private static ?array $aliases = null;

    public static function resolve(string $intentId): string
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return '';
        }
        $map = self::aliases();

        return $map[$intentId] ?? $intentId;
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        if (self::$aliases !== null) {
            return self::$aliases;
        }

        $path = dirname(__DIR__) . '/SubIntentEngine/schemas/intent-aliases.yaml';
        self::$aliases = [];
        if (!is_file($path)) {
            return self::$aliases;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('IntentAliasCatalog: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$aliases;
        }
        if (!is_array($data)) {
            return self::$aliases;
        }
        $raw = $data['aliases'] ?? [];
        if (!is_array($raw)) {
            return self::$aliases;
        }
        foreach ($raw as $from => $to) {
            $from = is_string($from) ? trim($from) : '';
            $to = is_string($to) ? trim($to) : '';
            if ($from !== '' && $to !== '') {
                self::$aliases[$from] = $to;
            }
        }

        return self::$aliases;
    }

    public static function resetCacheForTests(): void
    {
        self::$aliases = null;
    }
}
