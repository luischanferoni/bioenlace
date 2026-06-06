<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\models\Servicio;

/**
 * Resuelve rol/servicios sugeridos a partir del draft de triage y filtra listados de autogestión.
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
    public function resolverParaDraft(array $draft): array
    {
        $map = new ReservaTriageServicioMapService();
        $rol = $this->resolverRolDesdeDraft($draft);
        $eligibleIds = ServiciosEfectorAutogestionListadoService::idsServiciosDistintosAceptaTurnos();
        $matchedIds = $this->idsServicioParaRol($rol, $eligibleIds);

        return [
            'rol' => $rol,
            'rol_label' => $map->getLabelForRol($rol),
            'id_servicios' => $matchedIds,
            'filtrado_aplicado' => $matchedIds !== [],
        ];
    }

    /**
     * Filtra items ui_json según rol sugerido por triage (estricto: sin fallback a todos los servicios).
     *
     * @param list<array{id: string, name: string}> $items
     * @param array<string, mixed> $draft
     * @return list<array{id: string, name: string}>
     */
    public function filtrarItemsUiJson(array $items, array $draft): array
    {
        if ($items === [] || !$this->draftTieneTriageRelevante($draft)) {
            return $items;
        }

        $res = $this->resolverParaDraft($draft);
        if ($res['id_servicios'] === []) {
            return [];
        }

        $allow = array_flip($res['id_servicios']);
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
     * Mensaje cuando el filtro por triage no encontró servicios con turnos habilitados.
     *
     * @param array<string, mixed> $draft
     */
    public function mensajeListaVaciaParaDraft(array $draft): string
    {
        $res = $this->resolverParaDraft($draft);
        $label = $res['rol_label'] !== '' ? $res['rol_label'] : 'este tipo de atención';

        return 'No hay servicios de ' . $label
            . ' con turnos habilitados en este momento. Consultá con tu centro de salud o administración.';
    }

    /**
     * @param array<string, mixed> $draft mutado in-place
     */
    public function aplicarFlagsEnDraft(array &$draft): void
    {
        if (!$this->draftTieneTriageRelevante($draft)) {
            return;
        }

        $res = $this->resolverParaDraft($draft);
        $draft['servicio_reserva_rol'] = $res['rol'];
        if (count($res['id_servicios']) === 1) {
            $draft['id_servicio_sugerido'] = (string) $res['id_servicios'][0];
        }
    }

    /**
     * @param array<string, mixed> $draft
     */
    public function resolverRolDesdeDraft(array $draft): string
    {
        $map = new ReservaTriageServicioMapService();
        $catalog = new ReservaTurnoTriageCatalogService();

        foreach ($this->codigosCasoDesdeDraft($draft) as $code) {
            $node = $catalog->findNode($code);
            if ($node === null) {
                continue;
            }
            $rol = isset($node['suggests_servicio_rol'])
                ? trim((string) $node['suggests_servicio_rol'])
                : '';
            if ($rol !== '') {
                return $rol;
            }
        }

        return $map->getDefaultRol();
    }

    /**
     * @param list<int> $eligibleIds servicios con turnos habilitados
     * @return list<int>
     */
    public function idsServicioParaRol(string $rol, array $eligibleIds): array
    {
        $rol = trim($rol);
        if ($rol === '' || $eligibleIds === []) {
            return [];
        }

        $map = new ReservaTriageServicioMapService();
        $criteria = $map->getMatchCriteriaForRol($rol);
        if ($criteria === null) {
            return [];
        }

        $patterns = $criteria['nombre_patterns'];
        $itemNames = $criteria['item_names'];
        if ($patterns === [] && $itemNames === []) {
            return [];
        }

        $rows = Servicio::find()
            ->where(['id_servicio' => $eligibleIds])
            ->all();

        $out = [];
        foreach ($rows as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            if ($this->servicioCoincide($servicio, $patterns, $itemNames)) {
                $out[] = (int) $servicio->id_servicio;
            }
        }

        sort($out);

        return array_values(array_unique($out));
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

    /**
     * Más específico primero (detalle > zona > raíz).
     *
     * @param array<string, mixed> $draft
     * @return list<string>
     */
    private function codigosCasoDesdeDraft(array $draft): array
    {
        $ordered = ['triage_detalle', 'triage_zona', 'triage_raiz', 'triage_evolucion', 'triage_alarmas'];
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
     * @param list<string> $patterns
     * @param list<string> $itemNames
     */
    private function servicioCoincide(Servicio $servicio, array $patterns, array $itemNames): bool
    {
        $nombre = mb_strtolower(trim((string) $servicio->nombre));
        foreach ($patterns as $pattern) {
            $p = mb_strtolower(trim($pattern));
            if ($p !== '' && str_contains($nombre, $p)) {
                return true;
            }
        }

        $item = trim((string) ($servicio->item_name ?? ''));
        if ($item !== '' && in_array($item, $itemNames, true)) {
            return true;
        }

        return false;
    }
}
