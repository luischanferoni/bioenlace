<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\ReservaTriageCodigoServicioRol;

/**
 * Resuelve rol/especialidad sugerida desde el draft de triage (códigos más específicos primero).
 */
final class ReservaTriageServicioRolResolver
{
    /**
     * @param array<string, mixed> $draft
     */
    public function resolveDesdeDraft(array $draft): ReservaTriageServicioResolucion
    {
        $map = new ReservaTriageServicioMapService();
        $codigoResolutor = '';
        $rolIdeal = $this->resolverRolIdealDesdeDraft($draft, $codigoResolutor);
        $rolLabel = $map->getLabelForRol($rolIdeal);
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $autogestion = $map->permiteAutogestionPaciente($rolIdeal);
        $idsReservables = $autogestion ? $map->idsServicioParaRol($rolIdeal, $eligibleIds) : [];

        $mensajeOrientacion = null;
        $mensajeLista = null;

        if (!$autogestion) {
            $hubLabel = $map->getLabelForRol($map->getHubRol());
            $mensajeOrientacion = 'Por lo que contás, lo más adecuado es '
                . $rolLabel
                . '. No podés reservar turno directo con esa especialidad desde la app: '
                . 'pedí primero turno con '
                . $hubLabel
                . ' para que te evalúen y te deriven si corresponde.';
        } elseif ($idsReservables === []) {
            $mensajeOrientacion = 'No hay turnos de '
                . $rolLabel
                . ' habilitados en este momento en ningún centro de salud. '
                . 'Consultá con tu centro de salud o administración.';
        } else {
            $mensajeLista = 'Según lo que indicaste, estos servicios corresponden a '
                . $rolLabel
                . '. Elegí uno para continuar.';
        }

        return new ReservaTriageServicioResolucion(
            $rolIdeal,
            $rolLabel,
            $codigoResolutor,
            $autogestion && $idsReservables !== [],
            $idsReservables,
            $mensajeOrientacion,
            $mensajeLista,
        );
    }

    /**
     * IDs de servicios hub (Medicina clínica) para teleconsulta agregada u otros flujos hub-only.
     *
     * @param array<string, mixed> $draft
     * @return list<int>
     */
    public function idsServiciosHubParaDraft(array $draft): array
    {
        $map = new ReservaTriageServicioMapService();
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();

        return $map->idsServicioParaRol($map->getHubRol(), $eligibleIds);
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function resolverRolIdealDesdeDraft(array $draft, string &$codigoResolutor): string
    {
        foreach ($this->codigosPorEspecificidad($draft) as $code) {
            $rol = ReservaTriageCodigoServicioRol::rolParaCodigo($code)
                ?? ReservaTriageServicioRol::rolBuiltinParaCodigo($code);
            if ($rol !== null && trim($rol) !== '') {
                $codigoResolutor = $code;

                return trim($rol);
            }
        }

        $catalog = new ReservaTurnoTriageCatalogService();
        $compiled = $catalog->compileSelections($draft);
        $fromCatalog = trim((string) ($compiled['suggests_servicio_rol'] ?? ''));
        if ($fromCatalog !== '') {
            return $fromCatalog;
        }

        return (new ReservaTriageServicioMapService())->getDefaultRol();
    }

    /**
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    private function codigosPorEspecificidad(array $draft): array
    {
        $ordered = ['triage_detalle', 'triage_evolucion', 'triage_zona', 'triage_alarmas', 'triage_raiz'];
        $out = [];
        foreach ($ordered as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }
}
