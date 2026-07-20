<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\Turno;

/**
 * Opciones de modalidad para el paciente al reservar (presencial / teleconsulta / async).
 */
final class ReservaModalidadAtencionService
{
    /**
     * @param array<string, mixed> $draft
     * @return list<array{code: string, label: string}>
     */
    public function opcionesParaDraft(array $draft): array
    {
        $triageCatalog = new ReservaTurnoTriageCatalogService();
        $compiled = $triageCatalog->compileSelections($draft);
        if (!empty($compiled['reserva_triage_halt'])) {
            return [];
        }

        $elegibilidadService = new TeleconsultaElegibilidadService();
        $res = $elegibilidadService->resolverParaDraft($draft);
        $elegClinica = (string) ($res['elegibilidad_clinica'] ?? '');

        $catalog = new ReservaModalidadAtencionCatalogService();
        $out = [];
        $presencial = $catalog->opcion(ReservaModalidadAtencionCatalogService::CODE_PRESENCIAL);
        if ($presencial !== null) {
            $out[] = $presencial;
        }

        foreach ($elegibilidadService->opcionesModalidadParaDraft($draft) as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === Turno::TIPO_ATENCION_TELECONSULTA) {
                $opt = $catalog->opcion(ReservaModalidadAtencionCatalogService::CODE_TELECONSULTA);
                if ($opt !== null) {
                    $out[] = $opt;
                }
            }
        }

        if (in_array($elegClinica, $catalog->elegibilidadesParaAsync(), true)
            && $this->asyncPermitidoParaTriageRaiz($draft, $catalog)
        ) {
            $async = $catalog->opcion(ReservaModalidadAtencionCatalogService::CODE_ASYNC);
            if ($async !== null) {
                $out[] = $async;
            }
        }

        return $this->deduplicarPorCode($out);
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function asyncPermitidoParaTriageRaiz(
        array $draft,
        ReservaModalidadAtencionCatalogService $catalog
    ): bool {
        $allowed = $catalog->triageRaicesParaAsync();
        if ($allowed === []) {
            return true;
        }
        $raiz = trim((string) ($draft['triage_raiz'] ?? ''));

        return $raiz !== '' && in_array($raiz, $allowed, true);
    }

    /**
     * Flags de draft para el asistente y UI.
     *
     * @param array<string, mixed> $draft mutado in-place
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        (new TeleconsultaElegibilidadService())->aplicarFlagsEnDraft($draft);

        $opciones = $this->opcionesParaDraft($draft);
        $codes = array_column($opciones, 'code');

        $draft['async_ofrecible'] = in_array(ReservaModalidadAtencionCatalogService::CODE_ASYNC, $codes, true)
            ? '1'
            : '0';

        $draft['modalidad_paso_requerido'] = count($opciones) > 1 ? '1' : '0';

        if (count($opciones) === 1 && ($opciones[0]['code'] ?? '') === ReservaModalidadAtencionCatalogService::CODE_PRESENCIAL) {
            $draft['tipo_atencion'] = Turno::TIPO_ATENCION_PRESENCIAL;
        }
    }

    /**
     * @param list<array{code: string, label: string}> $rows
     * @return list<array{code: string, label: string}>
     */
    private function deduplicarPorCode(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = $row;
        }

        return $out;
    }
}
