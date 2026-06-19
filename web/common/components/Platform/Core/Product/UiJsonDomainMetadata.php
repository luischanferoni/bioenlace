<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de carpetas JSON UI ({@see ProductMetadataPaths::uiJsonDomainsFile()}).
 */
final class UiJsonDomainMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function domainForEntity(string $entity): ?string
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return null;
        }
        $map = self::loadConfig()['entity_domains'] ?? [];
        if (!is_array($map)) {
            return null;
        }
        $domain = $map[$entity] ?? null;

        return is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
    }

    public static function clinicalActionIdPrefix(): string
    {
        $prefix = trim((string) (self::loadConfig()['action_id_parse']['clinical_prefix'] ?? ''));

        return $prefix !== '' ? $prefix : 'clinical';
    }

    public static function templateAliasAction(string $entity, string $action): ?string
    {
        $entity = strtolower(trim($entity));
        $action = trim($action);
        if ($entity === '' || $action === '') {
            return null;
        }
        $aliases = self::loadConfig()['action_template_aliases'] ?? [];
        if (!is_array($aliases)) {
            return null;
        }
        $key = $entity . '/' . $action;
        $target = $aliases[$key] ?? null;

        return is_string($target) && trim($target) !== '' ? trim($target) : null;
    }

    public static function templateFolderForEntity(string $entity): string
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return '';
        }
        $aliases = self::loadConfig()['entity_folder_aliases'] ?? [];
        if (!is_array($aliases)) {
            return $entity;
        }
        $target = $aliases[$entity] ?? null;

        return is_string($target) && trim($target) !== '' ? trim($target) : $entity;
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
            'entity_domains' => [],
            'action_id_parse' => [],
            'action_template_aliases' => [],
            'entity_folder_aliases' => [],
        ];

        $path = ProductMetadataPaths::uiJsonDomainsFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('UiJsonDomainMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['entity_domains', 'action_id_parse', 'action_template_aliases', 'entity_folder_aliases'] as $key) {
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
