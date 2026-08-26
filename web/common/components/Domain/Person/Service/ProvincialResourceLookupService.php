<?php

namespace common\components\Domain\Person\Service;

use common\models\GeoRecursoInstitucional;
use common\models\GeoRecursoTipoAlias;
use common\models\Provincia;

/**
 * Lookup de recursos institucionales desde BD (no metadata YAML).
 */
final class ProvincialResourceLookupService
{
    /**
     * @return array<string, mixed>|null
     */
    public function findForProvincia(int $idProvincia, string $resourceType, ?string $queryHint = null): ?array
    {
        $provincia = Provincia::findOne($idProvincia);
        if ($provincia === null) {
            return null;
        }
        $provincia->populateRelation('pais', $provincia->pais);

        $type = trim($resourceType);
        if ($type === '' && $queryHint !== null) {
            $type = $this->inferTypeFromQuery($queryHint) ?? '';
        }
        if ($type === '') {
            return null;
        }

        if ($queryHint !== null && !$this->queryMatchesType($queryHint, $type)) {
            return null;
        }

        $row = GeoRecursoInstitucional::findOne([
            'tipo' => $type,
            'id_pais' => (int) $provincia->id_pais,
            'id_provincia' => $idProvincia,
        ]);
        if ($row === null) {
            $row = GeoRecursoInstitucional::find()
                ->where([
                    'tipo' => $type,
                    'id_pais' => (int) $provincia->id_pais,
                ])
                ->andWhere(['id_provincia' => null])
                ->one();
        }
        if ($row === null) {
            return null;
        }

        $pais = $provincia->pais;

        return [
            'tipo' => $type,
            'id_provincia' => $idProvincia,
            'provincia' => (string) $provincia->nombre,
            'cod_indec' => (string) $provincia->cod_indec,
            'id_pais' => (int) $provincia->id_pais,
            'iso2' => $pais !== null ? (string) $pais->iso2 : null,
            'recurso' => [
                'nombre' => (string) $row->nombre,
                'direccion' => $row->direccion,
                'telefono' => $row->telefono,
            ],
        ];
    }

    private function inferTypeFromQuery(string $query): ?string
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return null;
        }
        foreach (GeoRecursoTipoAlias::find()->all() as $aliasRow) {
            $a = mb_strtolower(trim((string) $aliasRow->alias));
            if ($a !== '' && str_contains($q, $a)) {
                return (string) $aliasRow->tipo;
            }
        }

        return null;
    }

    private function queryMatchesType(string $query, string $tipo): bool
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return true;
        }
        $aliases = GeoRecursoTipoAlias::find()->where(['tipo' => $tipo])->all();
        if ($aliases === []) {
            return true;
        }
        foreach ($aliases as $aliasRow) {
            $a = mb_strtolower(trim((string) $aliasRow->alias));
            if ($a !== '' && str_contains($q, $a)) {
                return true;
            }
        }

        return false;
    }
}
