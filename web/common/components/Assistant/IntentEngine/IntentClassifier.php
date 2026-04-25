<?php

namespace common\components\Assistant\IntentEngine;

use Yii;
use common\components\Ai\IAManager;

/**
 * Clasificación en dos fases: reglas sobre keywords del catálogo, luego IA solo entre ítems permitidos.
 */
final class IntentClassifier
{
    private const RULES_MIN_SCORE = 30;
    private const RULES_HIGH_CONFIDENCE = 0.7;

    /**
     * @return array{item:UiActionCatalogItem,confidence:float,method:string}|null
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

        foreach ($item->keywords as $keyword) {
            $keywordLower = mb_strtolower(trim($keyword), 'UTF-8');
            if ($keywordLower !== '' && mb_stripos($messageLower, $keywordLower) !== false) {
                $score += 20;
            }
        }

        return $score;
    }

    /**
     * @return array{item:UiActionCatalogItem,confidence:float,method:string}|null
     */
    private static function classifyByAi(string $message, UiActionCatalog $catalog, ?array $rulesHint): ?array
    {
        try {
            $list = array_map(static function (UiActionCatalogItem $i) {
                return $i->toPromptArray();
            }, $catalog->items);

            $catalogJson = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $hintText = '';
            if ($rulesHint !== null) {
                $hintText = "\nPista reglas: action_id sugerido '{$rulesHint['item']->action_id}' (confianza {$rulesHint['confidence']}).";
            }

            $prompt = <<<PROMPT
Asocia el siguiente mensaje del usuario con EXACTAMENTE un elemento del catálogo JSON.
Solo puedes elegir action_id que existan en el catálogo. Si no encaja ninguno, usa action_id "NONE".

Mensaje: "{$message}"
{$hintText}

Catálogo (lista completa permitida para este usuario):
{$catalogJson}

Responde ÚNICAMENTE con este JSON:
{
  "action_id": "valor exacto del catálogo o NONE",
  "confidence": 0.0,
  "reasoning": "breve"
}
PROMPT;

            $iaResponse = IAManager::consultarIA($prompt, 'intent-engine-classification', 'analysis');
            if (!$iaResponse || !is_array($iaResponse)) {
                return null;
            }

            $actionId = $iaResponse['action_id'] ?? null;
            $confidence = isset($iaResponse['confidence']) ? (float) $iaResponse['confidence'] : 0.7;

            if ($actionId === 'NONE' || $actionId === null || $actionId === '') {
                return null;
            }

            $item = $catalog->byActionId[(string) $actionId] ?? null;
            if ($item === null) {
                Yii::warning("IntentClassifier: IA devolvió action_id no permitido: {$actionId}", 'intent-engine');
                return null;
            }

            return [
                'item' => $item,
                'confidence' => max(0.0, min(1.0, $confidence)),
                'method' => 'ai',
            ];
        } catch (\Throwable $e) {
            Yii::error('IntentClassifier: ' . $e->getMessage(), 'intent-engine');
            return null;
        }
    }
}

