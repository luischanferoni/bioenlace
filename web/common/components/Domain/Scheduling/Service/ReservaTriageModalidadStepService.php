<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Paso de modalidad (presencial / teleconsulta): definido en el flow, no en catálogo clínico.
 */
final class ReservaTriageModalidadStepService
{
    public const STEP_ID = 'modalidad';

    public const DRAFT_FIELD = 'tipo_atencion';

    public const TITLE = '¿Cómo preferís la atención?';

    /**
     * @return array{title: string, draft_field: string}
     */
    public static function stepDefinition(): array
    {
        return [
            'title' => self::TITLE,
            'draft_field' => self::DRAFT_FIELD,
        ];
    }

    public static function isModalidadStep(string $stepId): bool
    {
        return trim($stepId) === self::STEP_ID;
    }
}
