<?php

namespace common\components\Assistant\Service;

/**
 * Normaliza claves del draft del asistente (Encounter/CarePlan vs legacy).
 */
final class AssistantDraftNormalizer
{
    /** Claves de control del snapshot que no deben quedar en draft clínico/operativo. */
    private const CONTROL_KEYS = [
        'intent_id' => true,
        'flow_key' => true,
        'subintent_id' => true,
        'content' => true,
        'interaction' => true,
        'hints' => true,
    ];

    /**
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    public static function normalize(array $draft): array
    {
        foreach (array_keys(self::CONTROL_KEYS) as $key) {
            unset($draft[$key]);
        }

        if (self::isEmpty($draft, 'encounter_id') && !self::isEmpty($draft, 'id_consulta')) {
            $draft['encounter_id'] = trim((string) $draft['id_consulta']);
        }

        if (self::isEmpty($draft, 'care_plan_id') && !self::isEmpty($draft, 'id_care_plan')) {
            $draft['care_plan_id'] = trim((string) $draft['id_care_plan']);
        }

        return $draft;
    }

    /**
     * @param mixed $value
     */
    public static function asOptionalString($value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $s = trim($value);

        return $s === '' ? null : $s;
    }

    /**
     * @param array<string, mixed> $arr
     */
    private static function isEmpty(array $arr, string $key): bool
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return true;
        }

        return self::asOptionalString($arr[$key]) === null;
    }
}
