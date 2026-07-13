<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Catálogo declarativo de atributos de efector ({@see ProductMetadataPaths::efectorAtributosFile()}).
 */
final class EfectorAtributosMetadata
{
    public const ATTR_DEPENDENCIA = 'dependencia';

    public const ATTR_TIPOLOGIA = 'tipologia';

    public const ATTR_ORIGEN_FINANCIAMIENTO = 'origen_financiamiento';

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * Opciones value => label para un atributo.
     * Si $current no está en el catálogo, se agrega para no perder el valor al editar.
     *
     * @return array<string, string>
     */
    public static function optionsFor(string $atributo, ?string $current = null): array
    {
        $items = self::loadConfig()['atributos'][$atributo] ?? null;
        if (!is_array($items)) {
            throw new \RuntimeException('Atributo de efector no declarado en metadata: ' . $atributo);
        }

        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? $value));
            $out[$value] = $label !== '' ? $label : $value;
        }

        $current = $current !== null ? trim($current) : '';
        if ($current !== '' && !isset($out[$current])) {
            $out[$current] = $current . ' (actual)';
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ProductMetadataPaths::efectorAtributosFile();
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró efector-atributos.yaml');
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::error('EfectorAtributosMetadata: ' . $e->getMessage(), __METHOD__);
            throw new \RuntimeException('efector-atributos.yaml inválido: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data) || !isset($data['atributos']) || !is_array($data['atributos'])) {
            throw new \RuntimeException('efector-atributos.yaml debe declarar atributos.');
        }

        self::$config = $data;

        return self::$config;
    }
}
