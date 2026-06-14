<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de expansión de params UI ({@see ProductMetadataPaths::uiScreenParamsFile()}).
 */
final class UiScreenParamsMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function matchesProvider(string $providerKey, string $entity, string $action): bool
    {
        $providerKey = trim($providerKey);
        $entity = strtolower(trim($entity));
        $action = trim($action);
        if ($providerKey === '' || $entity === '' || $action === '') {
            return false;
        }

        $rules = self::loadConfig()['param_expanders'][$providerKey] ?? null;
        if (!is_array($rules)) {
            return false;
        }

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (strtolower(trim((string) ($rule['entity'] ?? ''))) !== $entity) {
                continue;
            }
            foreach ($rule['actions'] ?? [] as $ruleAction) {
                if (is_string($ruleAction) && trim($ruleAction) === $action) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = ['param_expanders' => []];

        $path = ProductMetadataPaths::uiScreenParamsFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('UiScreenParamsMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (is_array($data) && isset($data['param_expanders']) && is_array($data['param_expanders'])) {
            self::$config['param_expanders'] = $data['param_expanders'];
        }

        return self::$config;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
