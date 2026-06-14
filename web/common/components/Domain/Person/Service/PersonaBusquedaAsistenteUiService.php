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
     * @return list<array{id: string, name: string}>
     */
    public static function buscar(?string $q, int $limit = 200): array
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
