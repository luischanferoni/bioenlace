<?php

namespace common\components\Domain\Organization\Service;

use common\models\Barrios;
use common\models\Departamento;
use common\models\Localidad;

/**
 * Respuestas Kartik DepDrop ({@code output}/{@code selected}) para provincia → departamento → localidad → barrio.
 */
final class GeografiaDepdropService
{
    public const URL_DEPARTAMENTOS = '/api/v1/catalogos/departamentos-depdrop';
    public const URL_LOCALIDADES = '/api/v1/catalogos/localidades-depdrop';
    public const URL_BARRIOS = '/api/v1/catalogos/barrios-depdrop';

    /**
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: int|string, name: string}>|string, selected: string|int}
     */
    public static function departamentosResponse(array $post): array
    {
        if (isset($post['depdrop_parents'])) {
            $parents = $post['depdrop_parents'];
            if ($parents !== null && $parents[0] !== '' && $parents[0] !== null) {
                return [
                    'output' => self::departamentosPorProvincia((int) $parents[0]),
                    'selected' => '',
                ];
            }
        }

        return ['output' => '', 'selected' => ''];
    }

    /**
     * @return list<array{id: int|string, name: string}>
     */
    public static function departamentosPorProvincia(int $idProvincia): array
    {
        return Departamento::find()
            ->asArray()
            ->select(['id' => 'id_departamento', 'name' => 'nombre'])
            ->where(['id_provincia' => $idProvincia])
            ->orderBy('nombre')
            ->all();
    }

    /**
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: int|string, name: string}>|string, selected: string|int}
     */
    public static function localidadesResponse(array $post): array
    {
        if (isset($post['depdrop_parents'])) {
            $parents = $post['depdrop_parents'];
            $parentId = empty($parents[0]) ? null : $parents[0];
            if ($parentId !== null && $parentId !== '') {
                return [
                    'output' => self::localidadesPorDepartamento((int) $parentId),
                    'selected' => $parentId,
                ];
            }
        }

        return ['output' => '', 'selected' => ''];
    }

    /**
     * @return list<array{id: int|string, name: string}>
     */
    public static function localidadesPorDepartamento(int $idDepartamento): array
    {
        return Localidad::find()
            ->asArray()
            ->select(['id' => 'id_localidad', 'name' => 'nombre'])
            ->where(['id_departamento' => $idDepartamento])
            ->orderBy('nombre')
            ->all();
    }

    /**
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: int|string, name: string}>, selected: string|int}
     */
    public static function barriosResponse(array $post): array
    {
        $out = [];
        $selected = '';

        if (isset($post['depdrop_parents'])) {
            $ids = $post['depdrop_parents'];
            $idLocalidad = empty($ids[0]) ? null : $ids[0];

            if ($idLocalidad !== null && $idLocalidad !== '') {
                if (!empty($post['depdrop_params'])) {
                    $params = $post['depdrop_params'];
                    $selected = $params[1] ?? '';
                }

                $out = self::barriosPorLocalidad((int) $idLocalidad);
            }

            if (isset($post['id_localidad'])) {
                $out = self::barriosPorLocalidad((int) $post['id_localidad']);
            }
        }

        return ['output' => $out, 'selected' => $selected];
    }

    /**
     * @return list<array{id: int|string, name: string}>
     */
    public static function barriosPorLocalidad(int $idLocalidad): array
    {
        $out = [];
        $barrios = Barrios::find()
            ->select(['id_barrio', 'nombre'])
            ->where(['id_localidad' => $idLocalidad])
            ->orderBy('nombre')
            ->all();

        foreach ($barrios as $barrio) {
            $out[] = ['id' => $barrio->id_barrio, 'name' => $barrio->nombre];
        }

        return $out;
    }
}
