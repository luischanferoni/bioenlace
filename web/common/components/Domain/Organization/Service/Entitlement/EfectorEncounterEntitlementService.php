<?php

namespace common\components\Domain\Organization\Service\Entitlement;

use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\Clinical\EncounterDefinition;
use common\models\EfectorEncounterEntitlement;

/**
 * Qué encounter_class puede usar un efector según contrato comercial.
 */
final class EfectorEncounterEntitlementService
{
    /**
     * Códigos habilitados para el efector (para wizard de sesión / set-session).
     *
     * @return list<string>
     */
    public static function allowedEncounterClasses(int $idEfector): array
    {
        $all = array_keys(EncounterDefinition::ENCOUNTER_CLASS);
        if ($idEfector <= 0) {
            return $all;
        }

        $rows = EfectorEncounterEntitlement::findActivasPorEfector($idEfector);
        if ($rows === []) {
            return PricingPesByEncounterClassMetadata::defaultWhenEmptyAllowAll()
                ? $all
                : [];
        }

        $codes = [];
        foreach ($rows as $row) {
            $code = (string) $row->encounter_class;
            if ($code !== '' && isset(EncounterDefinition::ENCOUNTER_CLASS[$code])) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function isEncounterClassAllowed(int $idEfector, string $encounterClass): bool
    {
        if ($encounterClass === '') {
            return true;
        }

        return in_array($encounterClass, self::allowedEncounterClasses($idEfector), true);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertEncounterClassAllowed(int $idEfector, string $encounterClass): void
    {
        if ($encounterClass === '') {
            return;
        }
        if (!self::isEncounterClassAllowed($idEfector, $encounterClass)) {
            $label = EncounterDefinition::ENCOUNTER_CLASS[$encounterClass] ?? $encounterClass;
            throw new \InvalidArgumentException(
                'El tipo de atención «' . $label . '» no está contratado para este efector.'
            );
        }
    }

    /**
     * Tope de PES contratado para una clase (null = sin tope o no contratado).
     */
    public static function maxPesForClass(int $idEfector, string $encounterClass): ?int
    {
        foreach (EfectorEncounterEntitlement::findActivasPorEfector($idEfector) as $row) {
            if ((string) $row->encounter_class === $encounterClass) {
                return $row->max_pes !== null ? (int) $row->max_pes : null;
            }
        }

        return null;
    }

    /**
     * @return list<array{code: string, label: string, max_pes: int|null}>
     */
    public static function contractSummary(int $idEfector): array
    {
        $out = [];
        foreach (EfectorEncounterEntitlement::findActivasPorEfector($idEfector) as $row) {
            $code = (string) $row->encounter_class;
            $out[] = [
                'code' => $code,
                'label' => (string) (EncounterDefinition::ENCOUNTER_CLASS[$code] ?? $code),
                'max_pes' => $row->max_pes !== null ? (int) $row->max_pes : null,
            ];
        }

        return $out;
    }
}
