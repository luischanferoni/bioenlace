<?php

namespace common\components\Platform\Assistant\IntentEngine;

use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;

/**
 * Clasificación NL → intent del catálogo (solo keywords PHP).
 *
 * Ganador claro → lanza. Empate cercano → desambiguación con botones.
 * Sin IA de clasificación: si no hay match, el canal operativo hace no-match / sugerencias.
 */
final class IntentClassifier
{
    private const RULES_MIN_SCORE = 30;

    /** Margen mínimo entre 1.º y 2.º para considerar ganador claro. */
    private const CLEAR_MARGIN = 20;

    /** Si el 2.º está dentro de este margen del 1.º (ambos ≥ MIN), se desambigua. */
    private const CLOSE_MARGIN = 20;

    /**
     * @return array{
     *   item:UiActionCatalogItem,
     *   confidence:float,
     *   method:string,
     *   disambiguation?:array{
     *     text:string,
     *     remediation:list<array{id:string,label:string,intent_id:string,reset_flow:bool}>
     *   }
     * }|null
     */
    public static function classify(string $message, UiActionCatalog $catalog, int $userId = 0): ?array
    {
        if ($catalog->items === []) {
            return null;
        }

        $rules = self::classifyByRules($message, $catalog->items);

        return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
    }

    /**
     * @param UiActionCatalogItem[] $items
     * @return array<string, mixed>|null
     */
    private static function classifyByRules(string $message, array $items): ?array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $ranked = self::rankItems($messageLower, $items);
        if ($ranked === [] || $ranked[0]['s'] < self::RULES_MIN_SCORE) {
            return null;
        }

        $best = $ranked[0];
        $second = $ranked[1] ?? null;
        $margin = $second !== null ? ((int) $best['s'] - (int) $second['s']) : PHP_INT_MAX;

        if (
            $second !== null
            && (int) $second['s'] >= self::RULES_MIN_SCORE
            && $margin < self::CLEAR_MARGIN
        ) {
            $candidates = [];
            foreach ($ranked as $row) {
                if ((int) $row['s'] < self::RULES_MIN_SCORE) {
                    break;
                }
                if ((int) $best['s'] - (int) $row['s'] > self::CLOSE_MARGIN) {
                    break;
                }
                $candidates[] = $row['it'];
                if (count($candidates) >= 3) {
                    break;
                }
            }
            if (count($candidates) >= 2) {
                return self::buildRulesDisambiguation($candidates, $best['it']);
            }
        }

        $confidence = min((int) $best['s'] / 100, 1.0);
        if ($margin >= self::CLEAR_MARGIN || (int) $best['s'] >= 55) {
            $confidence = max($confidence, 0.75);
        }

        return [
            'item' => $best['it'],
            'confidence' => $confidence,
            'method' => 'rules',
        ];
    }

    /**
     * @param UiActionCatalogItem[] $items
     * @return list<array{s:int,it:UiActionCatalogItem}>
     */
    private static function rankItems(string $messageLower, array $items): array
    {
        $scored = [];
        foreach ($items as $item) {
            $score = self::scoreItem($messageLower, $item);
            if ($score > 0) {
                $scored[] = ['s' => $score, 'it' => $item];
            }
        }
        usort($scored, static function ($a, $b) {
            return (int) $b['s'] <=> (int) $a['s'];
        });

        return $scored;
    }

    /**
     * @param UiActionCatalogItem[] $candidates
     * @return array<string, mixed>
     */
    private static function buildRulesDisambiguation(array $candidates, UiActionCatalogItem $primary): array
    {
        $remediation = [];
        foreach ($candidates as $it) {
            $label = trim((string) $it->display_name);
            if ($label === '') {
                $label = $it->action_id;
            }
            $remediation[] = [
                'id' => $it->action_id,
                'label' => $label,
                'intent_id' => $it->action_id,
                'reset_flow' => true,
            ];
        }

        return [
            'item' => $primary,
            'confidence' => 0.55,
            'method' => 'rules_disambiguation',
            'disambiguation' => [
                'text' => '¿Cuál de estas opciones necesitás?',
                'remediation' => $remediation,
            ],
        ];
    }

    public static function scoreItemPublic(string $messageLower, UiActionCatalogItem $item): int
    {
        return self::scoreItem($messageLower, $item);
    }

    /**
     * @param UiActionCatalogItem[] $items
     * @return array<string, mixed>|null
     */
    public static function classifyAmongItems(string $message, array $items, UiActionCatalog $catalog, int $userId = 0): ?array
    {
        if ($items === []) {
            return null;
        }

        $rules = self::classifyByRules($message, $items);

        return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
    }

    private static function scoreItem(string $messageLower, UiActionCatalogItem $item): int
    {
        $score = 0;

        foreach ([$item->action_id, $item->display_name] as $s) {
            $s = mb_strtolower(trim((string) $s), 'UTF-8');
            if ($s !== '' && mb_stripos($messageLower, $s) !== false) {
                $score += 40;
            }
        }

        // Una sola mejor keyword (el preprocess ya normaliza ortografía; fold cubre acentos).
        $bestKeyword = 0;
        $messageFolded = self::foldAccents($messageLower);
        foreach ($item->keywords as $keyword) {
            $keywordLower = mb_strtolower(trim((string) $keyword), 'UTF-8');
            if ($keywordLower === '') {
                continue;
            }
            $keywordFolded = self::foldAccents($keywordLower);
            $hit = 0;
            if ($messageLower === $keywordLower || $messageFolded === $keywordFolded) {
                $hit = 60;
            } elseif (mb_stripos($messageLower, $keywordLower) !== false
                || mb_stripos($messageFolded, $keywordFolded) !== false) {
                $words = preg_split('/\s+/u', $keywordFolded, -1, PREG_SPLIT_NO_EMPTY);
                $wordCount = is_array($words) ? count($words) : 1;
                $hit = 15 + min(35, $wordCount * 8);
            }
            if ($hit > $bestKeyword) {
                $bestKeyword = $hit;
            }
        }

        return $score + $bestKeyword;
    }

    public static function messageSuggestsStaffAgendaEdit(string $message): bool
    {
        return ChatChannelPolicy::suggestsStaffAgendaEdit($message);
    }

    public static function messageSuggestsOwnAgendaEdit(string $message): bool
    {
        return ChatChannelPolicy::suggestsOwnAgendaEdit($message);
    }

    private static function foldAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    /**
     * @return UiActionCatalogItem[]
     */
    public static function suggestByRules(string $message, UiActionCatalog $catalog, int $limit = 6): array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $ranked = self::rankItems($messageLower, $catalog->items);
        $out = [];
        foreach (array_slice($ranked, 0, max(0, $limit)) as $row) {
            $out[] = $row['it'];
        }

        return $out;
    }
}
