<?php

namespace common\components\Assistant\EntryPoints\ClinicalEncounter;

use common\components\Services\Consulta\ConsultaProcesamientoService;

/**
 * Captura clínica en consulta (texto/audio → análisis IA → guardar).
 * Sin preprocess del chat asistente; pipeline propio vía {@see ConsultaProcesamientoService}.
 */
final class ClinicalEncounterEntry
{
    /**
     * POST /api/v1/consulta/analizar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function analizar(array $body): array
    {
        return (new ConsultaProcesamientoService())->analizar($body);
    }

    /**
     * POST /api/v1/consulta/guardar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function guardar(array $body): array
    {
        return (new ConsultaProcesamientoService())->guardar($body);
    }
}
