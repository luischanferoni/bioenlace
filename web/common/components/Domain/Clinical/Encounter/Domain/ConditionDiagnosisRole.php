<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * Roles de Condition en el acto (FHIR Diagnosis Role + uso Bioenlace).
 *
 * {@see https://hl7.org/fhir/valueset-diagnosis-role.html}
 */
final class ConditionDiagnosisRole
{
    /** Chief complaint — motivo de consulta (Encounter.reasonReference). */
    public const CHIEF_COMPLAINT = 'CC';

    public const PRINCIPAL = 'principal';
    public const SECONDARY = 'secondary';

    /** @var list<string> */
    public const DIAGNOSIS_LIKE = [
        self::PRINCIPAL,
        self::SECONDARY,
    ];

    public static function isChiefComplaint(?string $role): bool
    {
        return strtoupper(trim((string) $role)) === self::CHIEF_COMPLAINT;
    }

    public static function isDiagnosisLike(?string $role): bool
    {
        $r = strtolower(trim((string) $role));
        if ($r === '') {
            // Legacy rows without role treated as diagnosis.
            return true;
        }

        return in_array($r, self::DIAGNOSIS_LIKE, true);
    }
}
