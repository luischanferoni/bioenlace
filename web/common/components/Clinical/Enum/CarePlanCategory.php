<?php

namespace common\components\Clinical\Enum;

/**
 * Códigos `care_plan.category` — ver `web/docs/dominio/flows/care-plan-categories.md`.
 */
final class CarePlanCategory
{
    public const ACUTE_AMBULATORY = 'acute-ambulatory';
    public const CHRONIC = 'chronic';
    public const PROGRAM = 'program';
    public const INPATIENT = 'inpatient';
    public const POSTOPERATIVE = 'postoperative';
    public const PREVENTIVE = 'preventive';
    public const PALLIATIVE = 'palliative';
    public const ODONTOLOGY = 'odontology';
    public const OPHTHALMOLOGY = 'ophthalmology';
    public const MENTAL_HEALTH = 'mental-health';
    public const REHABILITATION = 'rehabilitation';
    public const NUTRITION = 'nutrition';
    public const OTHER = 'other';

    /** @var list<string> */
    public const ALL = [
        self::ACUTE_AMBULATORY,
        self::CHRONIC,
        self::PROGRAM,
        self::INPATIENT,
        self::POSTOPERATIVE,
        self::PREVENTIVE,
        self::PALLIATIVE,
        self::ODONTOLOGY,
        self::OPHTHALMOLOGY,
        self::MENTAL_HEALTH,
        self::REHABILITATION,
        self::NUTRITION,
        self::OTHER,
    ];

    /** No se cierran al finalizar un encounter ambulatorio suelto. */
    public const PERSISTENT = [
        self::CHRONIC,
        self::PROGRAM,
        self::INPATIENT,
        self::PALLIATIVE,
        self::PREVENTIVE,
    ];

    public static function isValid(string $category): bool
    {
        return in_array($category, self::ALL, true);
    }

    public static function completesOnEncounterClose(string $category): bool
    {
        return !in_array($category, self::PERSISTENT, true);
    }

    public static function isProgramLike(string $category): bool
    {
        return in_array($category, [self::PROGRAM, self::REHABILITATION, self::MENTAL_HEALTH, self::ODONTOLOGY], true);
    }
}
