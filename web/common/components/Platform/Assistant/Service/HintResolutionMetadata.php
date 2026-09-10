<?php

namespace common\components\Platform\Assistant\Service;

/**
 * Reglas de resolución de hints del asistente (intent_ids, ownership de entidades).
 *
 * El ownership entidad → providers se compone desde
 * {@see HintCandidateProviderInterface::declaredEntities()} vía el registry;
 * no hay mapa central a mano.
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
        return HintCandidateProviderRegistry::providerKeysDeclaringEntity($entity);
    }

    public static function resetCacheForTests(): void
    {
        HintCandidateProviderRegistry::resetForTests();
    }
}
