<?php

namespace common\components\Services\Efectores;

use common\models\Efector;
use yii\web\Request;

/**
 * Listados de efectores para UI JSON y endpoints de datos (filtros compartidos).
 */
final class EfectoresListadosService
{
    /**
     * @return array<string, string>
     */
    public static function extractFilters(Request $req, bool $nearby): array
    {
        $filters = [];

        foreach ([
            'id_localidad',
            'id_departamento',
            'localidad_nombre',
            'departamento_nombre',
            'id_servicio',
            'dependencia',
            'tipologia',
            'estado',
        ] as $k) {
            $v = self::reqParam($req, $k);
            if ($v !== null) {
                $filters[$k] = $v;
            }
        }

        $idServAsignado = self::reqParam($req, 'id_servicio_asignado');
        if (!isset($filters['id_servicio']) && $idServAsignado !== null) {
            $filters['id_servicio'] = $idServAsignado;
        }

        if ($nearby) {
            $lat = self::reqParam($req, 'latitud');
            $lng = self::reqParam($req, 'longitud');
            if ($lat !== null && $lng !== null) {
                $filters['latitud'] = $lat;
                $filters['longitud'] = $lng;
                $filters['radio_km'] = self::reqParam($req, 'radio_km') ?? '10';
                $filters['sort_by'] = 'distancia';
                $filters['sort_order'] = 'ASC';
            }
        }

        $filters['limit'] = self::reqParam($req, 'limit') ?? '200';

        return $filters;
    }

    public static function requireServicioId(Request $req): ?string
    {
        $id = self::reqParam($req, 'id_servicio');
        if ($id === null) {
            $id = self::reqParam($req, 'id_servicio_asignado');
        }
        if ($id === null) {
            return null;
        }
        if (!ctype_digit((string) $id)) {
            return null;
        }
        $n = (int) $id;

        return $n > 0 ? (string) $n : null;
    }

    /**
     * @return array{0: ?string, 1: ?string} [lat, lng]
     */
    public static function requireLatLng(Request $req): array
    {
        $lat = self::reqParam($req, 'latitud');
        $lng = self::reqParam($req, 'longitud');
        if ($lat === null || $lng === null) {
            return [null, null];
        }

        return [$lat, $lng];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function itemsForUi(?string $q, array $filters): array
    {
        $rows = Efector::liveSearch($q, $filters);
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $name = isset($r['text']) ? trim((string) $r['text']) : '';
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
            ];
        }

        return $out;
    }

    private static function reqParam(Request $req, string $name): ?string
    {
        $v = $req->get($name);
        if ($v === null || $v === '') {
            $v = $req->post($name);
        }
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
