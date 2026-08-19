<?php

namespace common\components\Platform\Assistant\Service;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Mapa inverso de sinónimos coloquiales → nombres institucionales de servicios.
 *
 * Dado un term del usuario (ej. "dentista"), devuelve los nombres de servicio
 * que podrían matchear (ej. ["odontologia"]).
 *
 * @see servicio-synonyms.yaml
 */
final class HintServiceSynonyms
{
    /** @var array<string, list<string>>|null alias_normalizado → [nombre_servicio, ...] */
    private static ?array $inverse = null;

    /**
     * Dado un term normalizado del usuario, devuelve nombres de servicio asociados.
     *
     * @return list<string> nombres de servicio (lowercase, sin tildes)
     */
    public static function servicioNamesForAlias(string $alias): array
    {
        $alias = self::normalize($alias);
        if ($alias === '') {
            return [];
        }

        $map = self::inverseMap();

        return $map[$alias] ?? [];
    }

    /**
     * Enriquece una lista de terms con los nombres de servicio que matchean como sinónimos.
     *
     * @param list<string> $terms
     * @return list<string> terms originales + nombres de servicio añadidos
     */
    public static function enrichTerms(array $terms): array
    {
        $extra = [];
        foreach ($terms as $t) {
            foreach (self::servicioNamesForAlias($t) as $name) {
                $extra[] = $name;
            }
        }

        if ($extra === []) {
            return $terms;
        }

        return array_values(array_unique(array_merge($terms, $extra)));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function inverseMap(): array
    {
        if (self::$inverse !== null) {
            return self::$inverse;
        }

        self::$inverse = [];

        $path = ProductMetadataPaths::servicioSynonymsFile();
        if (!is_file($path)) {
            return self::$inverse;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            return self::$inverse;
        }

        if (!is_array($data) || !isset($data['synonyms']) || !is_array($data['synonyms'])) {
            return self::$inverse;
        }

        foreach ($data['synonyms'] as $servicioName => $aliases) {
            $servicioNorm = self::normalize((string) $servicioName);
            if ($servicioNorm === '' || !is_array($aliases)) {
                continue;
            }
            foreach ($aliases as $alias) {
                $aliasNorm = self::normalize((string) $alias);
                if ($aliasNorm === '') {
                    continue;
                }
                if (!isset(self::$inverse[$aliasNorm])) {
                    self::$inverse[$aliasNorm] = [];
                }
                if (!in_array($servicioNorm, self::$inverse[$aliasNorm], true)) {
                    self::$inverse[$aliasNorm][] = $servicioNorm;
                }
            }
        }

        return self::$inverse;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        if ($s === '') {
            return '';
        }
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($s === false) {
            return '';
        }

        return preg_replace('/\s+/u', ' ', trim($s)) ?? '';
    }

    public static function resetForTests(): void
    {
        self::$inverse = null;
    }
}
