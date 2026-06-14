<?php

namespace common\components\Assistant\IntentEngine;

use common\components\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Motor genérico sobre metadata de producto ({@see ProductMetadataPaths::intentClassificationRulesFile()}).
 */
final class IntentClassificationRulesService
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function ruleMatches(string $ruleId, string $message): bool
    {
        $ruleId = trim($ruleId);
        if ($ruleId === '') {
            return false;
        }
        $folded = self::foldMessage($message);
        if ($folded === '') {
            return false;
        }
        $rule = self::matchRules()[$ruleId] ?? null;

        return is_array($rule) && self::evaluateMatchRule($rule, $folded);
    }

    /**
     * @param list<string> $ruleIds
     */
    public static function matchesAnyRule(string $message, array $ruleIds): bool
    {
        foreach ($ruleIds as $ruleId) {
            if (is_string($ruleId) && self::ruleMatches($ruleId, $message)) {
                return true;
            }
        }

        return false;
    }

    public static function scoreAdjustment(string $message, string $actionId): int
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return 0;
        }
        $folded = self::foldMessage($message);
        if ($folded === '') {
            return 0;
        }

        $total = 0;
        foreach (self::scoreAdjustments() as $block) {
            if (!is_array($block)) {
                continue;
            }
            $whenRule = trim((string) ($block['when_rule'] ?? ''));
            if ($whenRule === '' || !self::ruleMatches($whenRule, $message)) {
                continue;
            }
            $whenAlso = trim((string) ($block['when_also'] ?? ''));
            if ($whenAlso !== '' && !self::ruleMatches($whenAlso, $message)) {
                continue;
            }
            $whenNot = trim((string) ($block['when_not_rule'] ?? ''));
            if ($whenNot !== '' && self::ruleMatches($whenNot, $message)) {
                continue;
            }
            foreach ($block['apply'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $delta = (int) ($row['delta'] ?? 0);
                if ($delta === 0) {
                    continue;
                }
                $targetId = trim((string) ($row['intent_id'] ?? ''));
                $prefix = trim((string) ($row['intent_id_prefix'] ?? ''));
                if ($targetId !== '' && $targetId === $actionId) {
                    $total += $delta;
                    continue;
                }
                if ($prefix !== '' && strncmp($actionId, $prefix, strlen($prefix)) === 0) {
                    $total += $delta;
                }
            }
        }

        return $total;
    }

    /**
     * @return array{item: UiActionCatalogItem, confidence: float, method: string}|null
     */
    public static function resolveOperationalFallback(string $message, UiActionCatalog $catalog): ?array
    {
        foreach (self::operationalFallbacks() as $fb) {
            if (!is_array($fb)) {
                continue;
            }
            if (!empty($fb['requires_staff_data_access'])
                && !ChatPreprocessService::isStaffDataAccessOperationalQuery($message)) {
                continue;
            }
            $whenAny = $fb['when_any_rule'] ?? [];
            if (!is_array($whenAny) || !self::matchesAnyRule($message, $whenAny)) {
                continue;
            }
            $intentId = trim((string) ($fb['intent_id'] ?? ''));
            if ($intentId === '') {
                continue;
            }
            $item = $catalog->byActionId[$intentId] ?? null;
            if (!$item instanceof UiActionCatalogItem) {
                continue;
            }

            return [
                'item' => $item,
                'confidence' => (float) ($fb['confidence'] ?? 0.9),
                'method' => trim((string) ($fb['method'] ?? 'rules_declarative_fallback')),
            ];
        }

        return null;
    }

    /**
     * Pistas para el prompt de IA (sin intent_id fijos).
     *
     * @return list<string>
     */
    public static function aiPromptHintLines(): array
    {
        $out = [];
        foreach (self::matchRules() as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $hint = trim((string) ($rule['ai_hint'] ?? ''));
            if ($hint !== '') {
                $out[] = $hint;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'vocab' => [],
            'match_rules' => [],
            'score_adjustments' => [],
            'operational_fallbacks' => [],
        ];

        $path = \common\components\Core\Product\ProductMetadataPaths::intentClassificationRulesFile();
        if (!is_file($path)) {
            return self::$config;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('IntentClassificationRulesService: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }
        if (!is_array($data)) {
            return self::$config;
        }
        foreach (['vocab', 'match_rules', 'score_adjustments', 'operational_fallbacks'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function vocab(): array
    {
        $v = self::loadConfig()['vocab'] ?? [];

        return is_array($v) ? $v : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function matchRules(): array
    {
        $r = self::loadConfig()['match_rules'] ?? [];

        return is_array($r) ? $r : [];
    }

    /**
     * @return list<mixed>
     */
    private static function scoreAdjustments(): array
    {
        $r = self::loadConfig()['score_adjustments'] ?? [];

        return is_array($r) ? array_values($r) : [];
    }

    /**
     * @return list<mixed>
     */
    private static function operationalFallbacks(): array
    {
        $r = self::loadConfig()['operational_fallbacks'] ?? [];

        return is_array($r) ? array_values($r) : [];
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function evaluateMatchRule(array $rule, string $folded): bool
    {
        foreach ($rule['forbid_any'] ?? [] as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (self::patternMatches($key, $folded)) {
                return false;
            }
        }

        foreach ($rule['require_all'] ?? [] as $key) {
            if (!is_string($key) || !self::patternMatches($key, $folded)) {
                return false;
            }
        }

        $requireAny = $rule['require_any'] ?? [];
        if (is_array($requireAny) && $requireAny !== []) {
            $hit = false;
            foreach ($requireAny as $key) {
                if (is_string($key) && self::patternMatches($key, $folded)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }

        $phrases = $rule['require_phrase_any'] ?? null;
        if (is_string($phrases) && $phrases !== '') {
            $phraseList = self::vocab()[$phrases] ?? [];
            if (is_array($phraseList) && $phraseList !== []) {
                $hit = false;
                foreach ($phraseList as $phrase) {
                    if (is_string($phrase) && $phrase !== '' && str_contains($folded, self::foldAccents(mb_strtolower($phrase, 'UTF-8')))) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function patternMatches(string $vocabKey, string $folded): bool
    {
        $vocabKey = trim($vocabKey);
        if ($vocabKey === '') {
            return false;
        }
        $pattern = self::vocab()[$vocabKey] ?? null;
        if (!is_string($pattern) || trim($pattern) === '') {
            return false;
        }

        return @preg_match('/' . $pattern . '/u', $folded) === 1;
    }

    private static function foldMessage(string $message): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return '';
        }

        return self::foldAccents($lower);
    }

    private static function foldAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
