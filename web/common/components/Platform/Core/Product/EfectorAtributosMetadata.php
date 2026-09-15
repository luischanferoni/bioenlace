<?php

namespace common\components\Platform\Core\Product;

/**
 * Catálogo declarativo de atributos de efector ({@see \common\components\Domain\Organization\Domain\EfectorAtributosCatalog}).
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

        $data = \common\components\Domain\Organization\Domain\EfectorAtributosCatalog::config();
        if (!isset($data['atributos']) || !is_array($data['atributos'])) {
            throw new \RuntimeException('EfectorAtributosCatalog debe declarar atributos.');
        }

        self::$config = $data;

        return self::$config;
    }
}
