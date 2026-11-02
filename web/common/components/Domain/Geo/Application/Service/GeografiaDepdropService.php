<?php

namespace common\components\Domain\Geo\Application\Service;

use common\models\Geo\Barrios;
use common\models\Geo\Departamento;
use common\models\Geo\Localidad;
use common\models\Geo\Pais;
use common\models\Geo\Provincia;

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
     * DepDrop: parent = id_provincia → todas las localidades de esa provincia.
     *
     * @param array<string, mixed> $post
     * @return array{output: list<array{id: int|string, name: string}>|string, selected: string|int}
     */
    public static function localidadesPorProvinciaResponse(array $post): array
    {
        if (isset($post['depdrop_parents'])) {
            $parents = $post['depdrop_parents'];
            $parentId = empty($parents[0]) ? null : $parents[0];
            if ($parentId !== null && $parentId !== '') {
                $selected = '';
                if (!empty($post['depdrop_params'][0])) {
                    $selected = $post['depdrop_params'][0];
                }

                return [
                    'output' => self::localidadesPorProvincia((int) $parentId),
                    'selected' => $selected,
                ];
            }
        }

        return ['output' => '', 'selected' => ''];
    }

    /**
     * @return list<array{id: int|string, name: string}>
     */
    public static function localidadesPorProvincia(int $idProvincia): array
    {
        return Localidad::find()
            ->alias('l')
            ->innerJoin(['d' => Departamento::tableName()], 'd.id_departamento = l.id_departamento')
            ->asArray()
            ->select(['id' => 'l.id_localidad', 'name' => 'l.nombre'])
            ->where(['d.id_provincia' => $idProvincia])
            ->orderBy(['l.nombre' => SORT_ASC])
            ->all();
    }

    /**
     * Búsqueda acotada para el alta móvil de efectores.
     *
     * @return list<array{id: int, nombre: string, departamento: string, provincia: string}>
     */
    public static function buscarLocalidades(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $allowedCountries = array_values(array_filter(
            (array) (\Yii::$app->params['signupAllowedIso2'] ?? ['AR']),
            static fn ($value) => is_string($value) && strlen($value) === 2
        ));
        if ($allowedCountries === []) {
            return [];
        }

        return Localidad::find()
            ->alias('l')
            ->innerJoin(['d' => Departamento::tableName()], 'd.id_departamento = l.id_departamento')
            ->innerJoin(['p' => Provincia::tableName()], 'p.id_provincia = d.id_provincia')
            ->innerJoin(['country' => Pais::tableName()], 'country.id_pais = p.id_pais')
            ->asArray()
            ->select([
                'id' => 'l.id_localidad',
                'nombre' => 'l.nombre',
                'departamento' => 'd.nombre',
                'provincia' => 'p.nombre',
            ])
            ->where(['like', 'l.nombre', $query])
            ->andWhere(['country.iso2' => $allowedCountries])
            ->orderBy(['l.nombre' => SORT_ASC, 'p.nombre' => SORT_ASC])
            ->limit(20)
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
