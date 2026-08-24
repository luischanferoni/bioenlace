<?php

namespace common\components\Platform\Assistant\IntentEngine;

use Yii;
use common\components\Ai\IAManager;

/**
 * Clasificación NL → intent del catálogo.
 *
 * Reglas: score por keywords del intent (YAML descriptivo). Ganador claro → lanza.
 * Empate cercano entre vecinos → desambiguación con botones (sin boost/penalidad por intent_id).
 * IA solo si no hay señal suficiente por keywords.
 */
final class IntentClassifier
{
    private const RULES_MIN_SCORE = 30;

    /** Confianza mínima para aceptar ganador por reglas sin pasar por IA. */
    private const RULES_HIGH_CONFIDENCE = 0.7;

    /** Margen mínimo entre 1.º y 2.º para considerar ganador claro. */
    private const CLEAR_MARGIN = 20;

    /** Si el 2.º está dentro de este margen del 1.º (ambos ≥ MIN), se desambigua. */
    private const CLOSE_MARGIN = 20;

    /**
     * @return array{
     *   item:UiActionCatalogItem,
     *   confidence:float,
     *   method:string,
     *   ai?:array{
     *     system_why?:string,
     *     user_text?:string,
     *     assumptions?:list<string>
     *   },
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
        if ($rules !== null && isset($rules['disambiguation'])) {
            return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
        }
        if ($rules !== null && $rules['confidence'] >= self::RULES_HIGH_CONFIDENCE) {
            return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
        }

        $ai = self::classifyByAi($message, $catalog, $rules);
        if ($ai !== null) {
            return (new IntentFamilyClassificationService())->refine($ai, $message, $userId, $catalog);
        }

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
     * Clasificación sobre un subconjunto del catálogo (top-K): solo reglas PHP (keywords).
     *
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

        // Una sola mejor keyword: evita que tokens sueltos acumulados empaten frases específicas.
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
        return \common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy::suggestsStaffAgendaEdit($message);
    }

    public static function messageSuggestsOwnAgendaEdit(string $message): bool
    {
        return \common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy::suggestsOwnAgendaEdit($message);
    }

    private static function foldAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    /**
     * Sugerencias por reglas aunque no alcancen umbral (para `no_intent_match`).
     *
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

    /**
     * @return array{
     *   item:UiActionCatalogItem,
     *   confidence:float,
     *   method:string,
     *   ai?:array{
     *     system_why?:string,
     *     user_text?:string,
     *     assumptions?:list<string>
     *   },
     *   disambiguation?:array{text:string,remediation:list<array{id:string,label:string,intent_id:string,reset_flow:bool}>}
     * }|null
     */
    private static function classifyByAi(string $message, UiActionCatalog $catalog, ?array $rulesHint): ?array
    {
        try {
            $candidates = array_map(static function (UiActionCatalogItem $i) {
                return $i->toAiCandidateArray();
            }, $catalog->items);

            $hintPayload = null;
            if ($rulesHint !== null && ($rulesHint['item'] ?? null) instanceof UiActionCatalogItem) {
                $hintPayload = [
                    'id' => $rulesHint['item']->action_id,
                    'confidence' => $rulesHint['confidence'] ?? null,
                ];
            }

            $toon = json_encode(
                [
                    'm' => $message,
                    'hint' => $hintPayload,
                    'c' => $candidates,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $prompt = <<<PROMPT
Tarea: elegir el mejor intent para el mensaje del usuario.

Entrada TOON (JSON compacto):
{$toon}

Reglas:
- Solo puedes elegir un id que exista en c[*].id o "NONE".
- Usa "NONE" solo si el mensaje NO corresponde a ninguno de los intents.
- Usa s (intent_semantics) para razonar por objetivo, cómo se logra y restricciones. k son frases ancla.
- Si dos intents son plausibles y falta una condición clave, marca needs_disambiguation y propone opciones.
- En remediation[*].intent_id solo ids de c[*].id (nunca inventes ids).
- Nunca elijas un intent cuyo goal/how contradiga el mensaje; preferí intent_semantics sobre suposiciones.
Responde ÚNICAMENTE con JSON:
{
  "best_id": "id o NONE",
  "confidence": 0.0,
  "system_why": "1-3 frases para logs/telemetría. Debe citar goal/how/constraints cuando existan",
  "user_text": "1-2 frases aptas para mostrar al usuario",
  "assumptions": ["..."],
  "needs_disambiguation": false,
  "remediation": [
    { "id": "opcion", "label": "texto", "intent_id": "id", "reset_flow": true }
  ]
}
PROMPT;

            $iaResponse = IAManager::consultarIA($prompt, 'intent-engine-classification', 'analysis');
            if (!$iaResponse || !is_array($iaResponse)) {
                return null;
            }

            $actionId = $iaResponse['best_id'] ?? null;
            $confidence = isset($iaResponse['confidence']) ? (float) $iaResponse['confidence'] : 0.7;
            $systemWhy = isset($iaResponse['system_why']) && is_string($iaResponse['system_why']) ? trim($iaResponse['system_why']) : '';
            $userText = isset($iaResponse['user_text']) && is_string($iaResponse['user_text']) ? trim($iaResponse['user_text']) : '';
            $assumptions = [];
            if (isset($iaResponse['assumptions']) && is_array($iaResponse['assumptions'])) {
                foreach ($iaResponse['assumptions'] as $a) {
                    if (is_string($a) && trim($a) !== '') {
                        $assumptions[] = trim($a);
                    }
                }
            }

            $needsDisambiguation = !empty($iaResponse['needs_disambiguation']);
            $remediation = [];
            if (isset($iaResponse['remediation']) && is_array($iaResponse['remediation'])) {
                foreach ($iaResponse['remediation'] as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $rid = trim((string) ($r['id'] ?? ''));
                    $label = trim((string) ($r['label'] ?? ''));
                    $iid = trim((string) ($r['intent_id'] ?? ''));
                    if ($label === '' || $iid === '') {
                        continue;
                    }
                    if (!isset($catalog->byActionId[$iid])) {
                        continue;
                    }
                    if ($rid === '') {
                        $rid = $iid;
                    }
                    $remediation[] = [
                        'id' => $rid,
                        'label' => $label,
                        'intent_id' => $iid,
                        'reset_flow' => !empty($r['reset_flow']),
                    ];
                }
            }

            if ($actionId === 'NONE' || $actionId === null || $actionId === '') {
                return null;
            }

            $item = $catalog->byActionId[(string) $actionId] ?? null;
            if ($item === null) {
                Yii::warning("IntentClassifier: IA devolvió action_id no permitido: {$actionId}", 'intent-engine');
                return null;
            }

            $out = [
                'item' => $item,
                'confidence' => max(0.0, min(1.0, $confidence)),
                'method' => 'ai',
            ];
            if ($systemWhy !== '' || $userText !== '' || $assumptions !== []) {
                $out['ai'] = [
                    'system_why' => $systemWhy !== '' ? $systemWhy : null,
                    'user_text' => $userText !== '' ? $userText : null,
                    'assumptions' => $assumptions,
                ];
            }
            if ($needsDisambiguation && $remediation !== []) {
                $text = $userText !== '' ? $userText : 'Elegí una opción.';
                $out['disambiguation'] = [
                    'text' => $text,
                    'remediation' => $remediation,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Yii::error('IntentClassifier: ' . $e->getMessage(), 'intent-engine');
            return null;
        }
    }
}
