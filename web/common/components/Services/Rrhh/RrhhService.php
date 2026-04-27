<?php

namespace common\components\Services\Rrhh;

use common\models\Persona;
use common\models\RrhhEfector;

/**
 * Servicios de listado/búsqueda de RRHH para mini-UIs (ui_json) y endpoints de apoyo.
 *
 * Importante: sin HTTP (no HttpException). Validaciones por argumentos.
 */
final class RrhhService
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

        /** @var \yii\db\ActiveQuery $query */
        $query = RrhhEfector::find();
        $query->alias('re')
            ->with('persona')
            ->where(['re.id_efector' => $idEfector])
            ->andWhere(['re.deleted_at' => null]);

        $q = $q !== null ? trim((string) $q) : '';
        if ($q !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->joinWith('persona p')
                ->andWhere([
                    'or',
                    ['like', 'p.apellido', $term, false],
                    ['like', 'p.nombre', $term, false],
                    ['like', 'p.documento', $term, false],
                ]);
        }

        $rows = $query->orderBy(['re.id_rr_hh' => SORT_ASC])->limit($limit)->all();
        $items = [];
        foreach ($rows as $re) {
            $id = (string) (int) $re->id_rr_hh;
            $name = $re->persona !== null
                ? $re->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : ('RRHH #' . $id);
            $items[] = ['id' => $id, 'name' => $name];
        }

        return $items;
    }

    /**
     * Lista RRHH de un efector que tengan al menos un servicio con `servicios.acepta_turnos = SI`.
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

        /** @var \yii\db\ActiveQuery $query */
        $query = RrhhEfector::find();
        $query->alias('re')
            ->with('persona')
            ->innerJoin(
                'rrhh_servicio rs',
                'rs.id_rr_hh = re.id_rr_hh AND rs.deleted_at IS NULL'
            )
            ->innerJoin(
                'servicios s',
                's.id_servicio = rs.id_servicio AND s.acepta_turnos = "SI"'
            )
            ->where(['re.id_efector' => $idEfector])
            ->andWhere(['re.deleted_at' => null])
            ->groupBy(['re.id_rr_hh']);

        $q = $q !== null ? trim((string) $q) : '';
        if ($q !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->joinWith('persona p')
                ->andWhere([
                    'or',
                    ['like', 'p.apellido', $term, false],
                    ['like', 'p.nombre', $term, false],
                    ['like', 'p.documento', $term, false],
                ]);
        }

        $rows = $query->orderBy(['re.id_rr_hh' => SORT_ASC])->limit($limit)->all();
        $items = [];
        foreach ($rows as $re) {
            $id = (string) (int) $re->id_rr_hh;
            $name = $re->persona !== null
                ? $re->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
                : ('RRHH #' . $id);
            $items[] = ['id' => $id, 'name' => $name];
        }

        return $items;
    }
}

