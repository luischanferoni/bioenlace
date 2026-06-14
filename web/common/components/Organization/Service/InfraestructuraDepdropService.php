<?php

namespace common\components\Organization\Service;

use common\models\InfraestructuraSala;

/**
 * DepDrop piso → sala (filtros de internación / mapa de camas).
 */
final class InfraestructuraDepdropService
{
    public const URL_SALAS_POR_PISO = '/api/v1/catalogos/salas-por-piso-depdrop';

    /**
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: int|string, name: string}>|string, selected: string|int}
     */
    public static function salasPorPisoResponse(array $post): array
    {
        if (isset($post['depdrop_parents'])) {
            $parents = $post['depdrop_parents'];
            if ($parents !== null && $parents[0] !== '' && $parents[0] !== null) {
                return [
                    'output' => self::salasPorPiso((int) $parents[0]),
                    'selected' => '',
                ];
            }
        }

        return ['output' => '', 'selected' => ''];
    }

    /**
     * @return list<array{id: int|string, name: string}>
     */
    public static function salasPorPiso(int $idPiso): array
    {
        return InfraestructuraSala::find()
            ->asArray()
            ->select(['id' => 'id', 'name' => 'descripcion'])
            ->where(['id_piso' => $idPiso])
            ->all();
    }
}
