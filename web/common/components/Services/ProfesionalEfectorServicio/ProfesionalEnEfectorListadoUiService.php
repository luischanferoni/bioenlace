<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use yii\db\Expression;
use yii\db\Query;

/**
 * Listado/búsqueda de profesionales en un efector para mini-UIs (ui_json).
 *
 * Sin HttpException: validaciones por \InvalidArgumentException.
 */
final class ProfesionalEnEfectorListadoUiService
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public static function listarPorEfector(int $idEfector, ?string $q = null, int $limit = 200): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('idEfector inválido.');
        }
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $qNorm = $q !== null ? trim((string) $q) : '';

        /** @var \yii\db\ActiveQuery $query */
        $query = ProfesionalEfectorServicio::find()->alias('pes')
            ->with(['persona'])
            ->where(['pes.id_efector' => $idEfector])
            ->andWhere(['pes.deleted_at' => null]);

        if ($qNorm !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qNorm) . '%';
            $query->joinWith(['persona p'])
                ->andWhere([
                    'or',
                    ['like', 'p.apellido', $term, false],
                    ['like', 'p.nombre', $term, false],
                    ['like', 'p.documento', $term, false],
                ]);
        }

        $query->limit(min(500, max(200, $limit * 5)));

        /** @var ProfesionalEfectorServicio[] $all */
        $all = $query->all();
        $byPersona = [];
        foreach ($all as $pes) {
            $pid = (int) $pes->id_persona;
            if (!isset($byPersona[$pid]) || (int) $pes->id < (int) $byPersona[$pid]->id) {
                $byPersona[$pid] = $pes;
            }
        }

        $items = [];
        foreach ($byPersona as $pid => $pesRep) {
            $id = (string) (int) $pesRep->id;
            $name = $pesRep->persona !== null
                ? $pesRep->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : ('Profesional #' . $id);
            $meta = [
                'id_profesional_efector_servicio' => (int) $pesRep->id,
            ];
            $items[] = ['id' => $id, 'name' => $name, 'meta' => $meta];
        }

        usort($items, static function ($a, $b) {
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * Lista profesionales de un efector que tengan al menos un servicio con `servicios.acepta_turnos = SI`.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function listarPorEfectorAceptaTurnos(int $idEfector, ?string $q = null, int $limit = 200): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('idEfector inválido.');
        }
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $qNorm = $q !== null ? trim((string) $q) : '';

        /** @var \yii\db\ActiveQuery $query */
        $query = ProfesionalEfectorServicio::find()->alias('pes')
            ->with(['persona'])
            ->innerJoin(['s' => 'servicios'], 's.id_servicio = pes.id_servicio AND s.acepta_turnos = "SI"')
            ->where(['pes.id_efector' => $idEfector])
            ->andWhere(['pes.deleted_at' => null]);

        if ($qNorm !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qNorm) . '%';
            $query->joinWith(['persona p'])
                ->andWhere([
                    'or',
                    ['like', 'p.apellido', $term, false],
                    ['like', 'p.nombre', $term, false],
                    ['like', 'p.documento', $term, false],
                ]);
        }

        $query->limit(min(500, max(200, $limit * 5)));

        /** @var ProfesionalEfectorServicio[] $all */
        $all = $query->all();
        $byPersona = [];
        foreach ($all as $pes) {
            $pid = (int) $pes->id_persona;
            if (!isset($byPersona[$pid]) || (int) $pes->id < (int) $byPersona[$pid]->id) {
                $byPersona[$pid] = $pes;
            }
        }

        $items = [];
        foreach ($byPersona as $pid => $pesRep) {
            $id = (string) (int) $pesRep->id;
            $name = $pesRep->persona !== null
                ? $pesRep->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : ('Profesional #' . $id);
            $meta = [
                'id_profesional_efector_servicio' => (int) $pesRep->id,
            ];
            $items[] = ['id' => $id, 'name' => $name, 'meta' => $meta];
        }

        usort($items, static function ($a, $b) {
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * Autocomplete por efector + servicio:
     * Filas desde `profesional_efector_servicio`; `id` = id PES (string).
     *
     * @param array<string, mixed> $filters id_efector, id_servicio|id_servicio_asignado, acepta_turnos?, efector_nombre?, servicio_nombre?, sort_by?, sort_order?, limit?
     * @return list<array{id: string, text: string, id_profesional_efector_servicio: int}>
     */
    public static function autocompletePorEfectorServicio(string $q, array $filters): array
    {
        $q = trim($q);

        $query = (new Query())
            ->select([
                'pes.id AS pes_id',
                'pes.id_persona AS id_persona',
                new Expression(
                    'CONCAT(COALESCE(p.apellido,""), ", ", COALESCE(p.nombre,""), " ", COALESCE(p.otro_nombre,""), " - ", COALESCE(s.nombre, "")) AS text'
                ),
            ])
            ->from(['pes' => 'profesional_efector_servicio'])
            ->innerJoin(['p' => 'personas'], 'p.id_persona = pes.id_persona')
            ->innerJoin(['s' => 'servicios'], 's.id_servicio = pes.id_servicio')
            ->leftJoin(['e' => 'efectores'], 'e.id_efector = pes.id_efector')
            ->where(['pes.deleted_at' => null]);

        if (!empty($filters['id_efector'])) {
            $query->andWhere(['pes.id_efector' => $filters['id_efector']]);
        }
        $idServicio = $filters['id_servicio'] ?? $filters['id_servicio_asignado'] ?? null;
        if (!empty($idServicio)) {
            $query->andWhere(['pes.id_servicio' => $idServicio]);
        }
        if (!empty($filters['acepta_turnos'])) {
            $query->andWhere(['s.acepta_turnos' => (string) $filters['acepta_turnos']]);
        }
        if (!empty($filters['efector_nombre'])) {
            $query->andWhere(['like', 'e.nombre', '%' . $filters['efector_nombre'] . '%', false]);
        }
        if (!empty($filters['servicio_nombre'])) {
            $query->andWhere(['like', 's.nombre', '%' . $filters['servicio_nombre'] . '%', false]);
        }

        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', new Expression('CONCAT(p.apellido, " ", p.nombre)'), '%' . $q . '%', false],
                ['like', new Expression('CONCAT(p.nombre, " ", p.apellido)'), '%' . $q . '%', false],
                ['like', new Expression('CONCAT(p.nombre, " ", COALESCE(p.otro_nombre,""))'), '%' . $q . '%', false],
                ['like', new Expression('CONCAT(p.nombre, " ", COALESCE(p.otro_nombre,""), " ", p.apellido)'), '%' . $q . '%', false],
                ['like', 'p.nombre', '%' . $q . '%', false],
                ['like', 'p.otro_nombre', '%' . $q . '%', false],
                ['like', 'p.apellido', '%' . $q . '%', false],
                ['like', 'p.documento', '%' . $q . '%', false],
            ]);
        }

        $sortBy = $filters['sort_by'] ?? 'apellido';
        $sortOrder = isset($filters['sort_order']) && strtoupper((string) $filters['sort_order']) === 'DESC' ? SORT_DESC : SORT_ASC;
        switch ($sortBy) {
            case 'nombre':
                $orderBy = ['p.nombre' => $sortOrder, 'p.apellido' => SORT_ASC];
                break;
            case 'efector':
                $orderBy = ['e.nombre' => $sortOrder, 'p.apellido' => SORT_ASC, 'p.nombre' => SORT_ASC];
                break;
            case 'servicio':
                $orderBy = ['s.nombre' => $sortOrder, 'p.apellido' => SORT_ASC, 'p.nombre' => SORT_ASC];
                break;
            case 'apellido':
            default:
                $orderBy = ['p.apellido' => $sortOrder, 'p.nombre' => SORT_ASC];
                break;
        }
        $query->orderBy($orderBy);

        $limit = isset($filters['limit']) ? min((int) $filters['limit'], 200) : 5;
        if ($limit < 1) {
            $limit = 5;
        }
        $query->limit($limit);

        $rows = $query->all();
        $out = [];
        foreach ($rows as $row) {
            $pesId = (int) ($row['pes_id'] ?? 0);
            if ($pesId <= 0) {
                continue;
            }
            $item = [
                'id' => (string) $pesId,
                'text' => trim((string) ($row['text'] ?? '')) !== '' ? trim((string) $row['text']) : ('PES #' . $pesId),
                'id_profesional_efector_servicio' => $pesId,
            ];

            $out[] = $item;
        }

        return array_values($out);
    }

    /**
     * Servicios habilitados en el efector (tabla {@see ServiciosEfector}), para elegir asignación sin profesional previo.
     * Sin `q` devuelve todos los habilitados (hasta `$limit`), ordenados por nombre. Con `q` filtra por nombre.
     *
     * @return list<array{id: string, name: string, meta: array{acepta_turnos: string}}>
     */
    public static function listarServiciosHabilitadosPorEfector(int $idEfector, ?string $q = null, int $limit = 200): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('idEfector inválido.');
        }
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $q = $q !== null ? trim((string) $q) : '';

        /** @var \yii\db\ActiveQuery $query */
        $query = ServiciosEfector::findActive()->alias('se');
        $query->innerJoin(['s' => 'servicios'], 's.id_servicio = se.id_servicio')
            ->where(['se.id_efector' => $idEfector])
            ->orderBy(['s.nombre' => SORT_ASC])
            ->limit($limit);

        if ($q !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->andWhere(['like', 's.nombre', $term, false]);
        }

        $rows = $query->all();
        $items = [];
        foreach ($rows as $se) {
            $idServicio = (int) $se->id_servicio;
            if ($idServicio === 62) {
                continue;
            }
            $srv = $se->servicio;
            $nombre = $srv !== null ? (string) $srv->nombre : ('Servicio #' . $idServicio);
            $acepta = $srv !== null && strtoupper(trim((string) $srv->acepta_turnos)) === 'SI' ? 'SI' : 'NO';
            $items[] = [
                'id' => (string) $idServicio,
                'name' => $nombre,
                'meta' => ['acepta_turnos' => $acepta],
            ];
        }

        return $items;
    }

    /**
     * Ítems normalizados para `UiScreenService::withListBlockItems` (servicios del efector en {@see ServiciosEfector}).
     *
     * @return list<array{id: string, name: string, meta: array<string, mixed>}>
     */
    public static function uiJsonItemsServiciosHabilitadosEfector(int $idEfector, ?string $q = null): array
    {
        $raw = self::listarServiciosHabilitadosPorEfector($idEfector, $q);
        $uiItems = [];
        foreach ($raw as $it) {
            $uiItems[] = [
                'id' => (string) $it['id'],
                'name' => (string) $it['name'],
                'meta' => isset($it['meta']) && is_array($it['meta']) ? $it['meta'] : [],
            ];
        }

        return $uiItems;
    }
}
