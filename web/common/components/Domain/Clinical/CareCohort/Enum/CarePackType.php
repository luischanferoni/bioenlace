<?php

namespace common\components\Domain\Clinical\CareCohort\Enum;

final class CarePackType
{
    public const ASSISTANCE_QUESTIONS = 'assistance_questions';
    public const FOLLOWUP_PROGRAM = 'followup_program';
    public const EDUCATION_BUNDLE = 'education_bundle';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ASSISTANCE_QUESTIONS,
            self::FOLLOWUP_PROGRAM,
            self::EDUCATION_BUNDLE,
        ];
    }

    public static function iaContext(string $packType): string
    {
        switch ($packType) {
            case self::ASSISTANCE_QUESTIONS:
                return 'care-pack-assistance-batch';
            case self::FOLLOWUP_PROGRAM:
                return 'care-pack-followup-batch';
            case self::EDUCATION_BUNDLE:
                return 'care-pack-education-batch';
            default:
                throw new \InvalidArgumentException('pack_type desconocido: ' . $packType);
        }
    }
}
