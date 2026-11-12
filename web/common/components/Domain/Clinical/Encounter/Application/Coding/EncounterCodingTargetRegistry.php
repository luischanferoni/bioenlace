<?php

namespace common\components\Domain\Clinical\Encounter\Application\Coding;

/**
 * Registro de todo lo codificable de un encounter.
 *
 * El worker corre todos los targets en orden; si un target falla, el job se
 * reintenta completo (cada target es idempotente y salta lo ya codificado).
 */
final class EncounterCodingTargetRegistry
{
    /**
     * @return list<EncounterCodingTargetInterface>
     */
    public static function all(): array
    {
        return [
            new DiagnosisConditionCodingTarget(),
            new ChiefComplaintSnomedCodingTarget(),
            new TreatmentRequestSnomedCodingTarget(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (EncounterCodingTargetInterface $target): string => $target->key(),
            self::all()
        );
    }
}
