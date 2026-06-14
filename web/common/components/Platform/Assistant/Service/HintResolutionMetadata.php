<?php

namespace common\components\Platform\Assistant\Service;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata declarativa para hints ({@see ProductMetadataPaths::hintResolutionFile()}).
 */
final class HintResolutionMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function intentUsesServiciosAceptaTurnos(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        $block = self::schedulingSection()['servicios_acepta_turnos'] ?? [];
        if (!is_array($block)) {
            return false;
        }

        foreach ($block['intent_ids'] ?? [] as $id) {
            if (is_string($id) && trim($id) === $intentId) {
                return true;
            }
        }

        foreach ($block['intent_prefixes'] ?? [] as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($intentId, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function triageAtencionIntentId(): string
    {
        $id = trim((string) (self::schedulingSection()['triage_atencion_intent_id'] ?? ''));

        return $id !== '' ? $id : 'atencion.necesito-atencion';
    }

    /**
     * @return list<string>
     */
    public static function providerKeysForEntity(string $entity): array
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return [];
        }

        $map = self::loadConfig()['entity_ownership'] ?? [];
        if (!is_array($map)) {
            return [];
        }

        $keys = $map[$entity] ?? [];
        if (!is_array($keys)) {
            return [];
        }

        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && trim($key) !== '') {
                $out[] = trim($key);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function schedulingSection(): array
    {
        $section = self::loadConfig()['scheduling'] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'scheduling' => [],
            'entity_ownership' => [],
        ];

        $path = ProductMetadataPaths::hintResolutionFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('HintResolutionMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['scheduling', 'entity_ownership'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
