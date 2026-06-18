<?php

namespace common\components\Domain\Person\Service;

use common\models\Person\Persona;

/**
 * Items para listados ui_json del asistente (búsqueda de personas).
 */
final class PersonaBusquedaAsistenteUiService
{
    /**
     * Solo devuelve filas si `$q` no está vacío (evita listar todo el padrón sin criterio).
     *
     * @param int|null $excluirIdEfector Si > 0, omite personas con asignación PES activa en ese efector.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function buscar(?string $q, int $limit = 200, ?int $excluirIdEfector = null): array
    {
        $q = $q !== null ? trim($q) : '';
        if ($q === '') {
            return [];
        }
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        /** @var \yii\db\ActiveQuery $query */
        $query = Persona::find()->alias('p');
        $query->andWhere([
            'or',
            ['like', 'p.apellido', $term, false],
            ['like', 'p.nombre', $term, false],
            ['like', 'p.documento', $term, false],
        ]);

        $excluirIdEfector = $excluirIdEfector !== null ? (int) $excluirIdEfector : 0;
        if ($excluirIdEfector > 0) {
            $query->andWhere([
                'not exists',
                (new \yii\db\Query())
                    ->from(['pes_ex' => 'profesional_efector_servicio'])
                    ->where('pes_ex.id_persona = p.id_persona')
                    ->andWhere([
                        'pes_ex.id_efector' => $excluirIdEfector,
                        'pes_ex.deleted_at' => null,
                    ]),
            ]);
        }

        $rows = $query->orderBy(['p.apellido' => SORT_ASC, 'p.nombre' => SORT_ASC])->limit($limit)->all();

        $items = [];
        foreach ($rows as $persona) {
            $items[] = [
                'id' => (string) (int) $persona->id_persona,
                'name' => $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
            ];
        }

        return $items;
    }
}
