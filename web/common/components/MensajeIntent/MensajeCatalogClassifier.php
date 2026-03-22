<?php

namespace common\components\MensajeIntent;

use Yii;
use common\components\Ai\IAManager;

/**
 * Clasificación en dos fases: reglas sobre keywords/patterns del catálogo, luego IA solo entre ítems permitidos.
 */
final class MensajeCatalogClassifier
{
    private const RULES_MIN_SCORE = 30;
    private const RULES_HIGH_CONFIDENCE = 0.7;

    /**
     * @param MensajeCatalogItem[] $catalog
     * @return array{
     *   item:MensajeCatalogItem,
     *   confidence:float,
     *   method:string,
     *   category?:string,
     *   intent?:string
     * }
     */
    public static function classify(string $message, ?array $context, array $catalog): ?array
    {
        if ($catalog === []) {
            return null;
        }

        $rules = self::classifyByRules($message, $catalog);
        if ($rules !== null && $rules['confidence'] >= self::RULES_HIGH_CONFIDENCE) {
            return self::enrichWithCategoryIntent($rules);
        }

        $ai = self::classifyByAi($message, $context, $catalog, $rules);
        if ($ai !== null) {
            return self::enrichWithCategoryIntent($ai);
        }

        if ($rules !== null) {
            return self::enrichWithCategoryIntent($rules);
        }

        return null;
    }

    /**
     * @param MensajeCatalogItem[] $catalog
     */
    private static function classifyByRules(string $message, array $catalog): ?array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $best = null;
        $bestScore = 0;

        foreach ($catalog as $item) {
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

    private static function scoreItem(string $messageLower, MensajeCatalogItem $item): int
    {
        $score = 0;

        foreach ($item->keywords as $keyword) {
            $keywordLower = mb_strtolower($keyword, 'UTF-8');
            if ($keywordLower !== '' && mb_stripos($messageLower, $keywordLower) !== false) {
                $score += 20;
            }
        }

        foreach ($item->patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && @preg_match($pattern, $messageLower)) {
                $score += 30;
            }
        }

        switch ($item->priority) {
            case 'critical':
                $score += 50;
                break;
            case 'high':
                $score += 20;
                break;
            case 'medium':
                $score += 10;
                break;
        }

        return $score;
    }

    /**
     * @param MensajeCatalogItem[] $catalog
     */
    private static function classifyByAi(string $message, ?array $context, array $catalog, ?array $rulesHint): ?array
    {
        try {
            $byId = [];
            foreach ($catalog as $item) {
                $byId[$item->action_id] = $item;
            }

            $list = array_map(static function (MensajeCatalogItem $i) {
                return $i->toPromptArray();
            }, $catalog);

            $catalogJson = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $contextText = '';
            if ($context && !empty($context['current_intent'])) {
                $contextText = "\nContexto: intent previo '{$context['current_intent']}' (categoría '{$context['current_category']}').";
            }
            $hintText = '';
            if ($rulesHint !== null) {
                $hintText = "\nPista reglas: action_id sugerido '{$rulesHint['item']->action_id}' (confianza {$rulesHint['confidence']}).";
            }

            $prompt = <<<PROMPT
Asocia el siguiente mensaje del usuario con EXACTAMENTE un elemento del catálogo JSON.
Solo puedes elegir action_id que existan en el catálogo. Si no encaja ninguno, usa action_id "NONE".

Mensaje: "{$message}"
{$contextText}{$hintText}

Catálogo (lista completa permitida para este usuario):
{$catalogJson}

Responde ÚNICAMENTE con este JSON:
{
  "action_id": "valor exacto del catálogo o NONE",
  "confidence": 0.0,
  "reasoning": "breve"
}
PROMPT;

            $iaResponse = IAManager::consultarIA($prompt, 'mensaje-catalog-classification', 'analysis');
            if (!$iaResponse || !is_array($iaResponse)) {
                return null;
            }

            $actionId = $iaResponse['action_id'] ?? null;
            $confidence = isset($iaResponse['confidence']) ? (float) $iaResponse['confidence'] : 0.7;

            if ($actionId === 'NONE' || $actionId === null || $actionId === '') {
                return null;
            }

            if (!isset($byId[$actionId])) {
                Yii::warning("MensajeCatalogClassifier: IA devolvió action_id no permitido: {$actionId}", 'mensaje-catalog-classifier');

                return null;
            }

            return [
                'item' => $byId[$actionId],
                'confidence' => max(0.0, min(1.0, $confidence)),
                'method' => 'ai',
            ];
        } catch (\Throwable $e) {
            Yii::error('MensajeCatalogClassifier: ' . $e->getMessage(), 'mensaje-catalog-classifier');

            return null;
        }
    }

    private static function enrichWithCategoryIntent(array $row): array
    {
        $item = $row['item'];
        if ($item->isConversation()) {
            $row['category'] = $item->category;
            $row['intent'] = $item->intent;
        }

        return $row;
    }
}
