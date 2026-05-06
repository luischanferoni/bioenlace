<?php

namespace common\components\Assistant\SubIntentEngine;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Reglas de negocio declarativas en YAML (`business_rules`) evaluadas antes de entrar al flow.
 *
 * Cada regla con `when: pre_flow` puede disparar `kind=intent_remediation` en {@see IntentEngine}
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
        $path = __DIR__ . '/schemas/intents/' . $intentId . '.yaml';
        if (!is_file($path)) {
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
            case 'agenda_alta_vs_solo_horarios':
                return self::checkAgendaAltaVsSoloHorarios($ctx);
            default:
                Yii::warning('business_rules: checker desconocido: ' . $checker, __METHOD__);

                return false;
        }
    }

    /**
     * Mensaje tipo “cargar agenda / horarios para un médico” sin vocabulario de alta de RRHH:
     * conviene desambiguar entre flujo completo (crear/asociar) y solo edición de agenda.
     *
     * @param array<string, mixed> $ctx
     */
    private static function checkAgendaAltaVsSoloHorarios(array $ctx): bool
    {
        $intentId = (string) ($ctx['intent_id'] ?? '');
        if ($intentId !== 'agenda.crear-rrhh-flow') {
            return false;
        }
        $content = mb_strtolower(trim((string) ($ctx['content'] ?? '')), 'UTF-8');
        if ($content === '') {
            return false;
        }
        $mentionsAgenda = (bool) preg_match('/cargar|configurar|editar|modificar|actualizar/u', $content)
            && (bool) preg_match('/agenda|horario/u', $content);
        $mentionsMed = (bool) preg_match('/m[eé]dico|profesional|doctor/u', $content);
        $mentionsAlta = (bool) preg_match('/crear|agregar|nuevo|alta|asociar|recurso humano/u', $content);

        return ($mentionsAgenda || $mentionsMed) && !$mentionsAlta;
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
