<?php

namespace common\components\Assistant\Service;

/**
 * Fuzzy de términos (span + sinónimos) contra candidatos [{id, nombre}, ...].
 */
final class HintEntityMatcher
{
    private const MIN_SCORE = 0.72;

    /**
     * @param list<string> $terms normalizados + sinónimos
     * @param list<array<string, mixed>> $candidates cada fila: id + match_property
     * @return array{id: string, value: string, score: float}|null
     */
    public static function match(array $terms, array $candidates, string $matchProperty): ?array
    {
        $matchProperty = trim($matchProperty);
        if ($matchProperty === '' || $candidates === []) {
            return null;
        }

        $terms = self::normalizeTerms($terms);
        if ($terms === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? trim((string) $row['id']) : '';
            if ($id === '') {
                continue;
            }
            $label = isset($row[$matchProperty]) ? trim((string) $row[$matchProperty]) : '';
            if ($label === '' && isset($row['name'])) {
                $label = trim((string) $row['name']);
            }
            if ($label === '' && isset($row['nombre'])) {
                $label = trim((string) $row['nombre']);
            }
            if ($label === '') {
                continue;
            }

            $labelNorm = self::normalizeText($label);
            foreach ($terms as $term) {
                $score = self::scoreTermAgainstLabel($term, $labelNorm);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'id' => $id,
                        'value' => $label,
                        'score' => $score,
                    ];
                }
            }
        }

        if ($best === null || $bestScore < self::MIN_SCORE) {
            return null;
        }

        return $best;
    }

    /**
     * @param list<string> $terms
     * @return list<string>
     */
    private static function normalizeTerms(array $terms): array
    {
        $out = [];
        foreach ($terms as $t) {
            $n = self::normalizeText($t);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    private static function normalizeText(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        if ($s === '') {
            return '';
        }
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($s === false) {
            return mb_strtolower(trim($s), 'UTF-8');
        }

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private static function scoreTermAgainstLabel(string $term, string $labelNorm): float
    {
        if ($term === '' || $labelNorm === '') {
            return 0.0;
        }
        if ($term === $labelNorm) {
            return 1.0;
        }
        if (mb_strlen($term, 'UTF-8') >= 3 && mb_stripos($labelNorm, $term, 0, 'UTF-8') !== false) {
            return 0.92;
        }
        if (mb_strlen($labelNorm, 'UTF-8') >= 3 && mb_stripos($term, $labelNorm, 0, 'UTF-8') !== false) {
            return 0.88;
        }

        $pct = 0.0;
        similar_text($term, $labelNorm, $pct);

        return $pct / 100.0;
    }
}
