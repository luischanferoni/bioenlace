<?php

namespace common\components\Domain\Person\Service;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use common\models\Provincia;
use Yii;
use yii\helpers\Yaml;

/**
 * Lookup declarativo de recursos institucionales por provincia de contexto.
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

        $codIndec = (string) $provincia->cod_indec;
        $catalog = $this->loadCatalog();
        $type = trim($resourceType);
        if ($type === '' && $queryHint !== null) {
            $type = $this->inferTypeFromQuery($queryHint, $catalog) ?? '';
        }
        if ($type === '') {
            return null;
        }

        $def = $catalog['recursos'][$type] ?? null;
        if (!is_array($def)) {
            return null;
        }

        if ($queryHint !== null && !$this->queryMatchesAliases($queryHint, $def)) {
            return null;
        }

        $row = $def['por_cod_indec'][$codIndec] ?? null;
        if (!is_array($row)) {
            return null;
        }

        return [
            'tipo' => $type,
            'id_provincia' => $idProvincia,
            'provincia' => (string) $provincia->nombre,
            'cod_indec' => $codIndec,
            'recurso' => $row,
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     */
    private function inferTypeFromQuery(string $query, array $catalog): ?string
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return null;
        }
        $recursos = $catalog['recursos'] ?? [];
        if (!is_array($recursos)) {
            return null;
        }
        foreach ($recursos as $type => $def) {
            if (!is_array($def)) {
                continue;
            }
            if ($this->queryMatchesAliases($query, $def)) {
                return (string) $type;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $def
     */
    private function queryMatchesAliases(string $query, array $def): bool
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return true;
        }
        $aliases = $def['aliases'] ?? [];
        if (!is_array($aliases)) {
            return true;
        }
        foreach ($aliases as $alias) {
            if (!is_string($alias)) {
                continue;
            }
            $a = mb_strtolower(trim($alias));
            if ($a !== '' && str_contains($q, $a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCatalog(): array
    {
        $path = ProductMetadataPaths::recursosProvincialesFile();
        if (!is_file($path)) {
            return [];
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('ProvincialResourceLookup: ' . $e->getMessage(), __METHOD__);

            return [];
        }

        return is_array($data) ? $data : [];
    }
}
