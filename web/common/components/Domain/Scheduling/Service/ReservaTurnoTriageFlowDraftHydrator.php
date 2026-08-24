<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;

/**
 * Enriquece el draft del asistente tras pasos de triage (halt, banda, sugerencia teleconsulta).
 */
final class ReservaTurnoTriageFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options ignorado
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $content = isset($body['content']) ? trim((string) $body['content']) : '';
        $catalog = new ReservaTurnoTriageCatalogService();

        self::hydrateMaletarNuevoFromContent($draft, $content, $catalog);

        $compiled = $catalog->compileSelections($draft);

        $draft['reserva_triage_halt'] = $compiled['reserva_triage_halt'] ? '1' : '0';
        $draft['urgency_band'] = $compiled['urgency_band'];
        if ($compiled['suggests_tipo_atencion'] !== null && trim((string) ($draft['tipo_atencion'] ?? '')) === '') {
            $draft['tipo_atencion_sugerido'] = $compiled['suggests_tipo_atencion'];
        }

        (new \common\components\Domain\Clinical\Access\PedidoAtencionPacienteService())
            ->hidratarDesdeMensaje($draft, $content);
        (new TeleconsultaElegibilidadService())->aplicarFlagsEnDraft($draft);
        (new ReservaModalidadAtencionService())->aplicarFlagsEnDraft($draft);
        (new ReservaTriageServicioSugeridoService())->aplicarFlagsEnDraft($draft);

        $body['draft'] = $draft;
    }

    /**
     * Si el content describe síntomas y triage_raiz no está seteado,
     * preselecciona malestar_nuevo. Se ejecuta antes de compileSelections
     * para que el catálogo de triage ya tenga la raíz al compilar.
     *
     * @param array<string, mixed> $draft
     */
    private static function hydrateMaletarNuevoFromContent(
        array &$draft,
        string $content,
        ReservaTurnoTriageCatalogService $catalog
    ): void {
        if (trim((string) ($draft['triage_raiz'] ?? '')) !== '') {
            return;
        }
        if ($content === '') {
            return;
        }

        if (IntentClassificationRulesService::isClinicalSymptomContent($content)) {
            $draft['triage_raiz'] = 'malestar_nuevo';
            self::hydrateZonaFromContent($draft, $content, $catalog);
        }
    }

    /**
     * Intenta inferir triage_zona desde match_keywords del catálogo.
     * Solo si triage_zona no está seteado y triage_raiz = malestar_nuevo.
     *
     * @param array<string, mixed> $draft
     */
    private static function hydrateZonaFromContent(
        array &$draft,
        string $content,
        ReservaTurnoTriageCatalogService $catalog
    ): void {
        if (trim((string) ($draft['triage_zona'] ?? '')) !== '') {
            return;
        }

        $zona = $catalog->inferZonaCodeFromText($content);
        if ($zona !== null) {
            $draft['triage_zona'] = $zona;
        }
    }
}
