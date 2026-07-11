<?php

namespace common\components\Platform\Assistant\IntentEngine;

use Yii;
use common\components\Platform\Ai\IAManager;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;

/**
 * Clasificación: reglas sobre keywords del catálogo; IA solo en {@see classify()} (catálogo completo).
 * El canal operativo usa {@see classifyAmongItems()} sin segunda IA (match PHP sobre normalized_text).
 */
final class IntentClassifier
{
    private const RULES_MIN_SCORE = 30;
    private const RULES_HIGH_CONFIDENCE = 0.7;

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
        if ($rules !== null && $rules['confidence'] >= self::RULES_HIGH_CONFIDENCE) {
            return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
        }

        if (ChatPreprocessService::isStaffDataAccessOperationalQuery($message)) {
            $declarative = IntentClassificationRulesService::resolveOperationalFallback($message, $catalog);
            if ($declarative !== null) {
                return (new IntentFamilyClassificationService())->refine($declarative, $message, $userId, $catalog);
            }
        }

        $ai = self::classifyByAi($message, $catalog, $rules);
        if ($ai !== null) {
            return (new IntentFamilyClassificationService())->refine($ai, $message, $userId, $catalog);
        }

        return (new IntentFamilyClassificationService())->refine($rules, $message, $userId, $catalog);
    }

    /**
     * @param UiActionCatalogItem[] $items
     * @return array{item:UiActionCatalogItem,confidence:float,method:string}|null
     */
    private static function classifyByRules(string $message, array $items): ?array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            $score = self::scoreItem($messageLower, $item);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best === null || $bestScore < self::RULES_MIN_SCORE) {
            return null;
        }

        return [
            'item' => $best,
            'confidence' => min($bestScore / 100, 1.0),
            'method' => 'rules',
        ];
    }

    public static function scoreItemPublic(string $messageLower, UiActionCatalogItem $item): int
    {
        return self::scoreItem($messageLower, $item);
    }

    /**
     * Clasificación sobre un subconjunto del catálogo (top-K): solo reglas PHP (keywords / semántica YAML).
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

    /**
     * @param UiActionCatalogItem[] $items
     */
    private static function catalogSubset(UiActionCatalog $catalog, array $items): UiActionCatalog
    {
        $byId = [];
        foreach ($items as $it) {
            $byId[$it->action_id] = $it;
        }

        return UiActionCatalog::fromItems(array_values($items), $byId);
    }

    private static function scoreItem(string $messageLower, UiActionCatalogItem $item): int
    {
        $score = 0;

        // match por action_id y display_name
        foreach ([$item->action_id, $item->display_name] as $s) {
            $s = mb_strtolower(trim($s), 'UTF-8');
            if ($s !== '' && mb_stripos($messageLower, $s) !== false) {
                $score += 40;
            }
        }

        $messageFolded = self::foldAccents($messageLower);
        foreach ($item->keywords as $keyword) {
            $keywordLower = mb_strtolower(trim($keyword), 'UTF-8');
            if ($keywordLower === '') {
                continue;
            }
            $keywordFolded = self::foldAccents($keywordLower);
            if ($messageLower === $keywordLower || $messageFolded === $keywordFolded) {
                // Frase exacta (p. ej. «mis turnos»): supera RULES_MIN_SCORE sin depender solo del delta YAML.
                $score += 50;
                continue;
            }
            if (mb_stripos($messageLower, $keywordLower) !== false
                || mb_stripos($messageFolded, $keywordFolded) !== false) {
                $score += 20;
            }
        }

        $score += IntentClassificationRulesService::scoreAdjustment($messageLower, $item->action_id);

        return $score;
    }

    public static function messageSuggestsStaffAgendaEdit(string $message): bool
    {
        return IntentClassificationRulesService::ruleMatches('staff_agenda_config_edit', $message);
    }

    public static function messageSuggestsOwnAgendaEdit(string $message): bool
    {
        return IntentClassificationRulesService::ruleMatches('own_agenda_config_edit', $message);
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
        $scored = [];
        foreach ($catalog->items as $it) {
            $s = self::scoreItem($messageLower, $it);
            if ($s > 0) {
                $scored[] = ['s' => $s, 'it' => $it];
            }
        }
        usort($scored, static function ($a, $b) {
            return (int) $b['s'] <=> (int) $a['s'];
        });
        $out = [];
        foreach (array_slice($scored, 0, max(0, $limit)) as $row) {
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

            $toon = json_encode(
                [
                    'm' => $message,
                    'hint' => $rulesHint !== null ? [
                        'id' => $rulesHint['item']->action_id,
                        'confidence' => $rulesHint['confidence'],
                    ] : null,
                    'c' => $candidates,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $aiHints = IntentClassificationRulesService::aiPromptHintLines();
            $aiHintsBlock = '';
            if ($aiHints !== []) {
                $aiHintsBlock = "- Pistas declarativas de routing (prioridad sobre heurísticas propias):\n";
                foreach ($aiHints as $hint) {
                    $aiHintsBlock .= '  - ' . $hint . "\n";
                }
            }

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
{$aiHintsBlock}
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

