<?php

namespace common\components\Platform\Assistant\SubIntentEngine;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Reglas de negocio declarativas en YAML (`business_rules`) evaluadas antes de entrar al flow.
 *
 * Cada regla con `when: pre_flow` puede devolver `remediation[]` vía {@see IntentEngine} (sobre `interactive` en API).
 * si el checker asociado indica violación (desambiguación / guía).
 */
final class IntentBusinessRules
{
    /**
     * @param array<string, mixed> $draft
     * @return array{rule_id: string, text: string, remediation: list<array<string, mixed>>}|null
     */
    public static function evaluatePreFlow(string $intentId, string $content, array $draft, int $userId): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }
        $intent = self::loadIntentYaml($intentId);
        if ($intent === null) {
            return null;
        }
        $rules = $intent['business_rules'] ?? null;
        if (!is_array($rules) || $rules === []) {
            return null;
        }
        $ctx = [
            'intent_id' => $intentId,
            'content' => $content,
            'draft' => $draft,
            'user_id' => $userId,
        ];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (trim((string) ($rule['when'] ?? '')) !== 'pre_flow') {
                continue;
            }
            $checker = trim((string) ($rule['checker'] ?? ''));
            if ($checker === '') {
                continue;
            }
            if (!self::isCheckerViolated($checker, $rule, $ctx)) {
                continue;
            }
            $rid = trim((string) ($rule['id'] ?? $checker));
            $text = trim((string) ($rule['user_message'] ?? ''));
            if ($text === '') {
                $text = 'Elegí cómo querés continuar.';
            }
            $remediation = self::normalizeRemediation($rule['remediation'] ?? null);
            if ($remediation === []) {
                Yii::warning('business_rules: regla ' . $rid . ' sin remediation válida', __METHOD__);
                continue;
            }

            return [
                'rule_id' => $rid !== '' ? $rid : $checker,
                'text' => $text,
                'remediation' => $remediation,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadIntentYaml(string $intentId): ?array
    {
        $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
        if ($path === null || !is_file($path)) {
            return null;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::error('YAML inválido intent ' . $intentId . ': ' . $e->getMessage(), __METHOD__);

            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $ctx
     */
    private static function isCheckerViolated(string $checker, array $rule, array $ctx): bool
    {
        switch ($checker) {
            case 'content_regex':
                return self::checkContentRegex($rule, $ctx);
            default:
                Yii::warning('business_rules: checker desconocido: ' . $checker, __METHOD__);

                return false;
        }
    }

    /**
     * Checker genérico declarativo basado en regex sobre `content`.
     *
     * YAML esperado (dentro de la regla):
     * - `require_any`: lista de regex; al menos una debe matchear.
     * - `require_all`: lista de regex; todas deben matchear.
     * - `forbid_any`: lista de regex; ninguna debe matchear.
     *
     * Si no se provee ninguna condición, la regla NO aplica.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $ctx
     */
    private static function checkContentRegex(array $rule, array $ctx): bool
    {
        $content = mb_strtolower(trim((string) ($ctx['content'] ?? '')), 'UTF-8');
        if ($content === '') {
            return false;
        }

        $requireAny = isset($rule['require_any']) && is_array($rule['require_any']) ? $rule['require_any'] : [];
        $requireAll = isset($rule['require_all']) && is_array($rule['require_all']) ? $rule['require_all'] : [];
        $forbidAny = isset($rule['forbid_any']) && is_array($rule['forbid_any']) ? $rule['forbid_any'] : [];

        if ($requireAny === [] && $requireAll === [] && $forbidAny === []) {
            return false;
        }

        foreach ($forbidAny as $re) {
            $re = is_string($re) ? trim($re) : '';
            if ($re === '') {
                continue;
            }
            if (@preg_match('/' . $re . '/u', $content)) {
                return false;
            }
        }

        foreach ($requireAll as $re) {
            $re = is_string($re) ? trim($re) : '';
            if ($re === '') {
                continue;
            }
            if (!@preg_match('/' . $re . '/u', $content)) {
                return false;
            }
        }

        if ($requireAny !== []) {
            $matched = false;
            foreach ($requireAny as $re) {
                $re = is_string($re) ? trim($re) : '';
                if ($re === '') {
                    continue;
                }
                if (@preg_match('/' . $re . '/u', $content)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $raw
     * @return list<array{id: string, label: string, intent_id: string, reset_flow: bool}>
     */
    private static function normalizeRemediation($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = trim((string) ($r['id'] ?? ''));
            $label = trim((string) ($r['label'] ?? ''));
            $iid = trim((string) ($r['intent_id'] ?? ''));
            if ($label === '' || $iid === '') {
                continue;
            }
            if ($id === '') {
                $id = $iid;
            }
            $out[] = [
                'id' => $id,
                'label' => $label,
                'intent_id' => $iid,
                'reset_flow' => !empty($r['reset_flow']),
            ];
        }

        return $out;
    }
}
