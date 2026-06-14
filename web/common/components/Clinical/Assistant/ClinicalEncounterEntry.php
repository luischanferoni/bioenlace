<?php

namespace common\components\Clinical\Assistant;

use common\components\Clinical\Workflow\EncounterDocumentationService;

/**
 * Captura clínica en encounter (texto/audio → análisis IA → guardar).
 */
final class ClinicalEncounterEntry
{
    /**
     * POST /api/v1/clinical/encounter/analizar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function analizar(array $body): array
    {
        return (new EncounterDocumentationService())->analizar($body);
    }

    /**
     * POST /api/v1/clinical/encounter/guardar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function guardar(array $body): array
    {
        return (new EncounterDocumentationService())->guardar($body);
    }
}
