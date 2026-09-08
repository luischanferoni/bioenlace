<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Reglas de resolución de hints del asistente (intent_ids, ownership de entidades).
 */
final class HintResolutionMetadata
{
    /** @var list<string> */
    private const SERVICIOS_ACEPTA_TURNOS_INTENT_IDS = [
        'atencion.necesito-atencion',
        'turnos.crear-como-paciente',
        'turnos.crear-para-paciente',
    ];

    /** @var list<string> */
    private const SERVICIOS_ACEPTA_TURNOS_INTENT_PREFIXES = [
        'turnos.',
    ];

    private const TRIAGE_ATENCION_INTENT_ID = 'atencion.necesito-atencion';

    /** @var array<string, list<string>> */
    private const ENTITY_OWNERSHIP = [
        'servicio' => ['scheduling', 'organization'],
        'efector' => ['organization'],
        'profesional' => ['organization'],
        'persona' => ['person'],
    ];

    public static function intentUsesServiciosAceptaTurnos(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        foreach (self::SERVICIOS_ACEPTA_TURNOS_INTENT_IDS as $id) {
            if ($id === $intentId) {
                return true;
            }
        }

        foreach (self::SERVICIOS_ACEPTA_TURNOS_INTENT_PREFIXES as $prefix) {
            if ($prefix !== '' && str_starts_with($intentId, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function triageAtencionIntentId(): string
    {
        return self::TRIAGE_ATENCION_INTENT_ID;
    }

    /**
     * @return list<string>
     */
    public static function providerKeysForEntity(string $entity): array
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return [];
        }

        $keys = self::ENTITY_OWNERSHIP[$entity] ?? [];
        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && trim($key) !== '') {
                $out[] = trim($key);
            }
        }

        return $out;
    }

    public static function resetCacheForTests(): void
    {
        // Sin cache de archivo; no-op para compatibilidad de tests.
    }
}
