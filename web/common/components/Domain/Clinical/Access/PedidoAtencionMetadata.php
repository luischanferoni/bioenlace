<?php

namespace common\components\Domain\Clinical\Access;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de PedidoAtencion ({@see ProductMetadataPaths::pedidoAtencionFile()}).
 */
final class PedidoAtencionMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::load();
    }

    /**
     * @return list<string>
     */
    public static function allowedSystems(): array
    {
        $raw = self::load()['allowed_systems'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return CodingSystems::defaults();
        }
        $out = [];
        foreach ($raw as $uri) {
            $s = trim((string) $uri);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out !== [] ? $out : CodingSystems::defaults();
    }

    /**
     * @return array{code: string, code_system: string, display: string}|null
     */
    public static function defaultActoForModo(string $modo): ?array
    {
        $modo = strtolower(trim($modo));
        $defaults = self::load()['defaults_por_modo'] ?? null;
        if (!is_array($defaults) || !isset($defaults[$modo]) || !is_array($defaults[$modo])) {
            return null;
        }
        $row = $defaults[$modo];
        $code = trim((string) ($row['code'] ?? ''));
        $system = trim((string) ($row['code_system'] ?? ''));
        $display = trim((string) ($row['display'] ?? ''));
        if ($code === '' || $system === '' || !CodingSystems::isAllowed($system, self::allowedSystems())) {
            return null;
        }

        return [
            'code' => $code,
            'code_system' => $system,
            'display' => $display !== '' ? $display : $code,
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'allowed_systems' => CodingSystems::defaults(),
            'defaults_por_modo' => [],
            'modos' => [],
        ];

        $path = ProductMetadataPaths::pedidoAtencionFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('PedidoAtencionMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach (['allowed_systems', 'defaults_por_modo', 'modos'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }
}
