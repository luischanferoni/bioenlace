<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\ReservaTriageCodigoServicio;
use common\models\Servicio;

/**
 * Resuelve servicio(s) sugeridos desde el draft de triage (FK directa a {@see Servicio}).
 *
 * Acumula especialidades de todos los pasos del draft (puede ser una, dos o más).
 */
final class ReservaTriageServicioRolResolver
{
    /**
     * @param array<string, mixed> $draft
     */
    public function resolveDesdeDraft(array $draft): ReservaTriageServicioResolucion
    {
        $codigos = $this->codigosTriageEnDraft($draft);
        $codigoResolutor = $this->codigoResolutorDesdeDraft($draft, $codigos);
        $idsSugeridos = ReservaTriageCodigoServicio::idsParaCodigos($codigos);
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $idsElegibles = $this->intersectarElegibles($idsSugeridos, $eligibleIds);
        $idsReservables = $this->filtrarAutogestionPaciente($idsElegibles);

        $labelSugerido = $this->labelParaIds($idsSugeridos !== [] ? $idsSugeridos : $idsElegibles);
        $labelReservable = $this->labelParaIds($idsReservables);
        $hubLabel = $this->labelHub();

        $mensajeOrientacion = null;
        $mensajeLista = null;
        $tieneEspecialistaSinAutogestion = $idsElegibles !== [] && $idsReservables === [];

        if ($tieneEspecialistaSinAutogestion) {
            $mensajeOrientacion = 'Por lo que contás, lo más adecuado es '
                . ($labelSugerido !== '' ? $labelSugerido : 'esa especialidad')
                . '. No podés reservar turno directo con esa especialidad desde la app: '
                . 'pedí primero turno con '
                . $hubLabel
                . ' para que te evalúen y te deriven si corresponde.';
        } elseif ($idsReservables === [] && $idsSugeridos !== []) {
            $mensajeOrientacion = 'No hay turnos de '
                . ($labelSugerido !== '' ? $labelSugerido : 'esos servicios')
                . ' habilitados en este momento en ningún centro de salud. '
                . 'Consultá con tu centro de salud o administración.';
        } elseif ($idsReservables === [] && $idsSugeridos === []) {
            $idsReservables = $this->idsServiciosHub();
            $labelReservable = $this->labelParaIds($idsReservables);
            if ($idsReservables === []) {
                $mensajeOrientacion = 'No hay servicios de '
                    . $hubLabel
                    . ' habilitados para reserva en este momento. Consultá con tu centro de salud.';
            } else {
                $mensajeLista = 'Elegí un servicio para continuar.';
            }
        } else {
            $mensajeLista = 'Según lo que indicaste, estos servicios corresponden a '
                . ($labelReservable !== '' ? $labelReservable : 'tu consulta')
                . '. Elegí uno para continuar.';
        }

        $rolIdeal = $idsSugeridos !== [] ? (string) $idsSugeridos[0] : (string) ($idsReservables[0] ?? '');

        return new ReservaTriageServicioResolucion(
            $rolIdeal,
            $labelSugerido !== '' ? $labelSugerido : $labelReservable,
            $codigoResolutor,
            $idsReservables !== [],
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
        return $this->idsServiciosHub();
    }

    /**
     * @return list<int>
     */
    public function idsServiciosHub(): array
    {
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();

        return $this->filtrarAutogestionPaciente($eligibleIds);
    }

    /**
     * @param list<string> $codigosEnDraft
     */
    private function codigoResolutorDesdeDraft(array $draft, array $codigosEnDraft): string
    {
        foreach ($this->codigosPorEspecificidad($draft) as $code) {
            if (!in_array($code, $codigosEnDraft, true)) {
                continue;
            }
            if (ReservaTriageCodigoServicio::idsParaCodigo($code) !== []) {
                return $code;
            }
        }

        return $codigosEnDraft[0] ?? '';
    }

    /**
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    private function codigosTriageEnDraft(array $draft): array
    {
        return $this->codigosPorEspecificidad($draft);
    }

    /**
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    public function codigosTriagePublicosDesdeDraft(array $draft): array
    {
        return $this->codigosPorEspecificidad($draft);
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

    /**
     * @param list<int> $ids
     * @param list<int> $eligibleIds
     * @return list<int>
     */
    private function intersectarElegibles(array $ids, array $eligibleIds): array
    {
        if ($ids === []) {
            return [];
        }
        $allow = array_flip($eligibleIds);
        $out = [];
        foreach ($ids as $id) {
            if (isset($allow[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function filtrarAutogestionPaciente(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (Servicio::find()->where(['id_servicio' => $ids])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            if ($servicio->permiteReservaAutogestionPaciente()) {
                $out[] = (int) $servicio->id_servicio;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $ids
     */
    private function labelParaIds(array $ids): string
    {
        if ($ids === []) {
            return '';
        }
        $nombres = [];
        foreach (Servicio::find()->where(['id_servicio' => $ids])->orderBy(['nombre' => SORT_ASC])->all() as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            $nombre = trim((string) $servicio->nombre);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return implode(', ', array_values(array_unique($nombres)));
    }

    private function labelHub(): string
    {
        $ids = $this->idsServiciosHub();
        $label = $this->labelParaIds($ids);

        return $label !== '' ? $label : 'Medicina clínica';
    }
}
