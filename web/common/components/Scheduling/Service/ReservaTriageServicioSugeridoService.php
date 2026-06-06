<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\ConsultaDerivaciones;
use common\models\Servicio;
use common\models\Turno;

/**
 * Resuelve rol/servicios sugeridos a partir del draft de triage y filtra listados de autogestión.
 *
 * Modelo hub: autogestión paciente → solo Medicina clínica; especialistas vía derivación clínica.
 */
final class ReservaTriageServicioSugeridoService
{
    /**
     * @param array<string, mixed> $draft
     * @return array{
     *   rol: string,
     *   rol_label: string,
     *   id_servicios: list<int>,
     *   filtrado_aplicado: bool
     * }
     */
    public function resolverParaDraft(array $draft, bool $soloHubPaciente = false): array
    {
        $map = new ReservaTriageServicioMapService();
        $rol = $this->resolverRolDesdeDraft($draft, $soloHubPaciente);
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $matchedIds = $map->idsServicioParaRol($rol, $eligibleIds);

        return [
            'rol' => $rol,
            'rol_label' => $map->getLabelForRol($rol),
            'id_servicios' => $matchedIds,
            'filtrado_aplicado' => $matchedIds !== [],
        ];
    }

    /**
     * Lista ui_json solo con servicios del hub (Medicina clínica).
     *
     * @param list<array{id: string, name: string}> $items
     * @return list<array{id: string, name: string}>
     */
    public function filtrarItemsHubPaciente(array $items): array
    {
        return $this->filtrarItemsPorRol($items, (new ReservaTriageServicioMapService())->getHubRol());
    }

    /**
     * Filtra items ui_json según rol (estricto: sin fallback a todos los servicios).
     *
     * @param list<array{id: string, name: string}> $items
     * @param array<string, mixed> $draft
     * @return list<array{id: string, name: string}>
     */
    public function filtrarItemsUiJson(array $items, array $draft, bool $soloHubPaciente = false): array
    {
        if ($items === []) {
            return $items;
        }
        if (!$soloHubPaciente && !$this->draftTieneTriageRelevante($draft)) {
            return $items;
        }

        $res = $this->resolverParaDraft($draft, $soloHubPaciente);
        if ($res['id_servicios'] === []) {
            return [];
        }

        return $this->filtrarItemsPorIds($items, $res['id_servicios']);
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function mensajeListaVaciaParaDraft(array $draft, bool $soloHubPaciente = true): string
    {
        $res = $this->resolverParaDraft($draft, $soloHubPaciente);
        $label = $res['rol_label'] !== '' ? $res['rol_label'] : 'Medicina clínica';

        return 'No hay turnos de ' . $label
            . ' habilitados en este momento. Los especialistas atienden por derivación del médico clínico;'
            . ' consultá con tu centro de salud si necesitás ayuda.';
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        if (!$this->draftTieneTriageRelevante($draft)) {
            return;
        }

        $res = $this->resolverParaDraft($draft, true);
        $draft['servicio_reserva_rol'] = $res['rol'];
        $draft['reserva_modo_hub_paciente'] = '1';
        if (count($res['id_servicios']) === 1) {
            $draft['id_servicio_sugerido'] = (string) $res['id_servicios'][0];
        }
    }

    /**
     * Paciente autogestionando: solo hub o especialista con derivación vigente al servicio.
     */
    public function assertPacientePuedeReservarServicio(Turno $model): void
    {
        $idServicio = (int) ($model->id_servicio_asignado ?? 0);
        $idPersona = (int) ($model->id_persona ?? 0);
        $idEfector = (int) ($model->id_efector ?? 0);
        if ($idServicio <= 0 || $idPersona <= 0) {
            return;
        }

        $map = new ReservaTriageServicioMapService();
        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return;
        }

        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $rol = $map->resolveRolForServicio($servicio, $eligibleIds);
        if ($rol === null || $map->permiteAutogestionPaciente($rol)) {
            return;
        }

        if ($idEfector <= 0) {
            throw new \InvalidArgumentException(
                'Este servicio requiere derivación de Medicina clínica antes de reservar turno.'
            );
        }

        $pendientes = ConsultaDerivaciones::getDerivacionesPorPersona(
            $idPersona,
            $idEfector,
            $idServicio,
            ConsultaDerivaciones::ESTADO_EN_ESPERA
        );
        if ($pendientes === []) {
            throw new \InvalidArgumentException(
                'Los turnos con especialistas requieren derivación de un médico de Medicina clínica.'
                . ' Pedí turno con clínica primero o usá la derivación que te indicaron.'
            );
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function resolverRolDesdeDraft(array $draft, bool $soloHubPaciente = false): string
    {
        $map = new ReservaTriageServicioMapService();

        if ($soloHubPaciente || $this->draftTieneTriageRelevante($draft)) {
            return $map->getHubRol();
        }

        return $map->getDefaultRol();
    }

    /**
     * @param array<string, mixed> $params query/body de API
     * @return array<string, mixed>
     */
    public static function draftDesdeParamsTriage(array $params): array
    {
        $keys = [
            'triage_raiz',
            'triage_alarmas',
            'triage_zona',
            'triage_detalle',
            'triage_evolucion',
        ];
        $draft = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }
            $v = trim((string) $params[$key]);
            if ($v !== '') {
                $draft[$key] = $v;
            }
        }

        return $draft;
    }

    public static function esModoHubPaciente(array $params): bool
    {
        return trim((string) ($params['reserva_modo'] ?? '')) === 'hub_paciente';
    }

    public static function esModoTeleconsultaHub(array $params): bool
    {
        return trim((string) ($params['reserva_modo'] ?? '')) === 'teleconsulta_hub';
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @param list<int> $ids
     * @return list<array{id: string, name: string}>
     */
    private function filtrarItemsPorIds(array $items, array $ids): array
    {
        $allow = array_flip($ids);
        $filtered = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && isset($allow[$id])) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param list<array{id: string, name: string}> $items
     * @return list<array{id: string, name: string}>
     */
    private function filtrarItemsPorRol(array $items, string $rol): array
    {
        $map = new ReservaTriageServicioMapService();
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $ids = $map->idsServicioParaRol($rol, $eligibleIds);
        if ($ids === []) {
            return [];
        }

        return $this->filtrarItemsPorIds($items, $ids);
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function draftTieneTriageRelevante(array $draft): bool
    {
        foreach (['triage_raiz', 'triage_zona', 'triage_detalle'] as $key) {
            if (trim((string) ($draft[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
}
