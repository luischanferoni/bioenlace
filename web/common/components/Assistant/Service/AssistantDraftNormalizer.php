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
     * @param array<string, mixed> $arr
     */
    private static function isEmpty(array $arr, string $key): bool
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return true;
        }

        return trim((string) $arr[$key]) === '';
    }
}
