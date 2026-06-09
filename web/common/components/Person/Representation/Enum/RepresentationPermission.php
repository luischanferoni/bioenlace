<?php

namespace common\components\Person\Representation\Enum;

/**
 * Permisos v1 de representación (producto cerrado en plan FHIR).
 */
final class RepresentationPermission
{
    public const SCHEDULING_TURNO = 'scheduling.turno';
    public const CLINICAL_MOTIVOS = 'clinical.motivos';
    public const CLINICAL_CARE_PACK_ASSISTANCE = 'clinical.care_pack_assistance';
    public const CLINICAL_CARE_PLAN = 'clinical.care_plan';
    public const CLINICAL_HISTORIA_RESUMEN = 'clinical.historia_resumen';

    /**
     * @return list<string>
     */
    public static function v1Defaults(): array
    {
        return [
            self::SCHEDULING_TURNO,
            self::CLINICAL_MOTIVOS,
            self::CLINICAL_CARE_PACK_ASSISTANCE,
            self::CLINICAL_CARE_PLAN,
            self::CLINICAL_HISTORIA_RESUMEN,
        ];
    }
}
