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

        self::hydrateMaletarNuevoFromContent($draft, $content);

        $catalog = new ReservaTurnoTriageCatalogService();
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
    private static function hydrateMaletarNuevoFromContent(array &$draft, string $content): void
    {
        if (trim((string) ($draft['triage_raiz'] ?? '')) !== '') {
            return;
        }
        if ($content === '') {
            return;
        }

        if (IntentClassificationRulesService::isClinicalSymptomContent($content)) {
            $draft['triage_raiz'] = 'malestar_nuevo';
            self::hydrateZonaFromContent($draft, $content);
        }
    }

    /**
     * Intenta inferir triage_zona desde keywords en el mensaje del paciente.
     * Solo si triage_zona no está seteado y triage_raiz = malestar_nuevo.
     *
     * @param array<string, mixed> $draft
     */
    private static function hydrateZonaFromContent(array &$draft, string $content): void
    {
        if (trim((string) ($draft['triage_zona'] ?? '')) !== '') {
            return;
        }

        $lower = mb_strtolower(trim($content), 'UTF-8');
        $zona = self::inferirZonaDesdeTexto($lower);
        if ($zona !== null) {
            $draft['triage_zona'] = $zona;
        }
    }

    private static function inferirZonaDesdeTexto(string $lower): ?string
    {
        $map = [
            'zona_genitourinario' => [
                'embaraz', 'semanas de gestac', 'semanas y sangr', 'parto',
                'gineco', 'útero', 'utero', 'ovario', 'menstrua', 'regla',
                'vagina', 'vulva', 'flujo vaginal', 'mamografia', 'mamografía',
                'próstata', 'prostata', 'orina', 'urina', 'vejiga',
            ],
            'zona_cabeza_cuello' => [
                'cabeza', 'cuello', 'mareo', 'vértigo', 'vertigo', 'migraña', 'migraña',
                'jaqueca', 'nuca',
            ],
            'zona_pecho' => [
                'pecho', 'corazón', 'corazon', 'respira', 'pulmon', 'pulmón',
                'tos', 'ahogo', 'taquicardia',
            ],
            'zona_abdomen' => [
                'panza', 'abdomen', 'estómago', 'estomago', 'digestión', 'digestion',
                'náusea', 'nausea', 'vómito', 'vomito', 'diarrea', 'intestin',
            ],
            'zona_musculoesqueletico' => [
                'espalda', 'columna', 'hueso', 'músculo', 'musculo', 'articulac',
                'rodilla', 'tobillo', 'hombro', 'cadera', 'cintura', 'lumbar',
                'cervical', 'fractura', 'esguince',
            ],
            'zona_piel' => [
                'piel', 'sarpullido', 'erupción', 'erupcion', 'herida', 'quemadura',
                'roncha', 'picazón', 'picazon', 'eczema', 'psoriasis',
            ],
            'zona_sistemas' => [
                'ojo', 'vista', 'visión', 'vision', 'diente', 'muela', 'boca',
                'dental', 'oído', 'oido', 'garganta',
            ],
            'zona_general' => [
                'fiebre', 'cansancio', 'fatiga', 'decaimiento', 'malestar general',
            ],
        ];

        foreach ($map as $zona => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return $zona;
                }
            }
        }

        return null;
    }
}
