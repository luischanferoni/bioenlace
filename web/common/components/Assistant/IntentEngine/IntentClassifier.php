<?php

namespace common\components\Assistant\IntentEngine;

use Yii;
use common\components\Ai\IAManager;

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
    public static function classify(string $message, UiActionCatalog $catalog): ?array
    {
        if ($catalog->items === []) {
            return null;
        }

        $rules = self::classifyByRules($message, $catalog->items);
        if ($rules !== null && $rules['confidence'] >= self::RULES_HIGH_CONFIDENCE) {
            return $rules;
        }

        $ai = self::classifyByAi($message, $catalog, $rules);
        if ($ai !== null) {
            return $ai;
        }

        return $rules;
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
    public static function classifyAmongItems(string $message, array $items, UiActionCatalog $catalog): ?array
    {
        unset($catalog);
        if ($items === []) {
            return null;
        }

        return self::classifyByRules($message, $items);
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
            if (mb_stripos($messageLower, $keywordLower) !== false
                || mb_stripos($messageFolded, $keywordFolded) !== false) {
                $score += 20;
            }
        }

        $score += self::scoreDataAccessListVsInfo($messageLower, $item->action_id);
        $score += self::scoreStaffAgendaEditDispersa($messageLower, $item->action_id);

        return $score;
    }

    /**
     * Modificar agenda/horarios de un profesional ya asociado → edición dispersa, no alta PES.
     */
    private static function scoreStaffAgendaEditDispersa(string $messageLower, string $actionId): int
    {
        if ($actionId === 'data-access.editar' && self::messageSuggestsStaffAgendaEdit($messageLower)) {
            return 45;
        }

        if ($actionId === 'profesional-efector-servicio.crear-flow' && self::messageSuggestsStaffAgendaEdit($messageLower)) {
            return -30;
        }

        return 0;
    }

    public static function messageSuggestsStaffAgendaEdit(string $message): bool
    {
        $folded = self::foldAccents(mb_strtolower(trim($message), 'UTF-8'));
        if ($folded === '') {
            return false;
        }

        if (!preg_match('/\b(editar|modificar|actualizar|cambiar|ajustar|configurar)\b/u', $folded)) {
            return false;
        }

        if (!preg_match('/\b(agenda|horario|horarios|disponibilidad|cupo)\b/u', $folded)) {
            return false;
        }

        if (preg_match('/\b(crear|agregar|nuevo|nueva|alta|asociar|dar de alta)\b/u', $folded)) {
            return false;
        }

        if (preg_match('/\b(mi|mis)\s+(agenda|horario|horarios)\b/u', $folded)) {
            return false;
        }

        return preg_match('/\b(profesional|medico|doctor|personal)\b/u', $folded) === 1
            || preg_match('/\b(agenda|horario|horarios)\s+de\s+un\b/u', $folded) === 1
            || preg_match('/\b(agenda|horario|horarios)\s+del\b/u', $folded) === 1;
    }

    /**
     * Desambiguación staff: pedidos de listado nominado → data-access.listar; conteos → info.
     */
    private static function scoreDataAccessListVsInfo(string $messageLower, string $actionId): int
    {
        if ($actionId !== 'data-access.listar' && $actionId !== 'data-access.info') {
            return 0;
        }

        $wantsList = self::messageSuggestsStaffList($messageLower);
        $wantsCount = self::messageSuggestsStaffCount($messageLower);

        if ($actionId === 'data-access.listar') {
            if ($wantsList && !$wantsCount) {
                return 25;
            }
            if ($wantsList && $wantsCount) {
                return 10;
            }

            return 0;
        }

        // data-access.info
        if ($wantsCount) {
            return 15;
        }
        if ($wantsList && !$wantsCount) {
            return -20;
        }

        return 0;
    }

    private static function messageSuggestsStaffList(string $messageLower): bool
    {
        $folded = self::foldAccents($messageLower);

        return preg_match(
            '/\b(listar|mostrar|mostrame|ver listado|nombres de|quienes|quien es|quién es|plantilla)\b/u',
            $folded
        ) === 1
            || str_contains($folded, 'profesionales del centro')
            || str_contains($folded, 'medicos del centro');
    }

    private static function messageSuggestsStaffCount(string $messageLower): bool
    {
        $folded = self::foldAccents($messageLower);

        return preg_match(
            '/\b(cuantos|cuantos hay|total de|conteo|cantidad de|numero de|cuenta)\b/u',
            $folded
        ) === 1;
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
- Si el usuario pide modificar/editar agenda u horarios de un profesional (sin crear/alta), best_id = data-access.editar.

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
                    $iid = \common\components\Assistant\Catalog\IntentIdAliasResolver::resolve($iid);
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

