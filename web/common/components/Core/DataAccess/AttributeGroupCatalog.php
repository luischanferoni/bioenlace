<?php

namespace common\components\Core\DataAccess;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de grupos de atributos, métricas, grants por rol y planes de query.
 */
final class AttributeGroupCatalog
{
    private const FILE = 'attribute_groups_v1.yaml';

    /** @var array<string, mixed>|null */
    private static $cache;

    /**
     * @return array<string, mixed>|null
     */
    public function getMetric(string $metricId): ?array
    {
        $metricId = trim($metricId);
        $metrics = self::load()['metrics'] ?? [];
        if (!is_array($metrics) || !isset($metrics[$metricId]) || !is_array($metrics[$metricId])) {
            return null;
        }

        return $metrics[$metricId];
    }

    /**
     * Plan de compilación SQL/Yii para una métrica.
     *
     * @return array<string, mixed>|null
     */
    public function getMetricQueryPlan(string $metricId): ?array
    {
        $metric = $this->getMetric($metricId);
        if ($metric === null) {
            return null;
        }
        $query = $metric['query'] ?? null;

        return is_array($query) ? $query : null;
    }

    /**
     * @return array<string, mixed>|null definición de un filtro allowlisted
     */
    public function getFilterDefinition(string $metricId, string $filterKey): ?array
    {
        $plan = $this->getMetricQueryPlan($metricId);
        if ($plan === null) {
            return null;
        }
        $filters = $plan['filters'] ?? null;
        if (!is_array($filters) || !isset($filters[$filterKey]) || !is_array($filters[$filterKey])) {
            return null;
        }

        return $filters[$filterKey];
    }

    /**
     * Filtros con entity_group → clave para autorización (filterKey => entity.group).
     *
     * @return array<string, string>
     */
    public function filterEntityGroupMap(string $metricId): array
    {
        $plan = $this->getMetricQueryPlan($metricId);
        if ($plan === null) {
            return [];
        }
        $filters = $plan['filters'] ?? null;
        if (!is_array($filters)) {
            return [];
        }

        $out = [];
        foreach ($filters as $filterKey => $def) {
            if (!is_array($def)) {
                continue;
            }
            $group = trim((string) ($def['entity_group'] ?? ''));
            if ($group !== '') {
                $out[trim((string) $filterKey)] = $group;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null grant YAML estático (sin BD).
     */
    public function getYamlRoleGrant(string $roleName, string $entityGroupKey): ?array
    {
        $roleName = trim($roleName);
        $entityGroupKey = trim($entityGroupKey);
        $grants = self::load()['role_grants'][$roleName] ?? null;
        if (!is_array($grants) || !isset($grants[$entityGroupKey]) || !is_array($grants[$entityGroupKey])) {
            return null;
        }

        return $grants[$entityGroupKey];
    }

    /**
     * @deprecated use getYamlRoleGrant; permisos efectivos vía {@see AttributePermissionEvaluator}.
     *
     * @return array<string, mixed>|null
     */
    public function getRoleGrant(string $roleName, string $entityGroupKey): ?array
    {
        return $this->getYamlRoleGrant($roleName, $entityGroupKey);
    }

    /**
     * @return array<string, mixed>|null bloque output del plan query
     */
    public function getMetricOutputPlan(string $metricId): ?array
    {
        $plan = $this->getMetricQueryPlan($metricId);
        if ($plan === null) {
            return null;
        }
        $output = $plan['output'] ?? null;

        return is_array($output) ? $output : null;
    }

    public function getPresentationHandler(string $metricId): ?string
    {
        $metric = $this->getMetric($metricId);
        if ($metric === null) {
            return null;
        }
        $handler = trim((string) ($metric['presentation_handler'] ?? ''));

        return $handler !== '' ? $handler : null;
    }

    /**
     * Resuelve sexo_biologico (1 F, 2 M) desde mención NL usando sinónimos del catálogo.
     */
    public function resolveSexoBiologicoFromMention(string $mention): ?int
    {
        $mention = mb_strtolower(trim($mention), 'UTF-8');
        if ($mention === '') {
            return null;
        }

        $synonyms = self::load()['filter_synonyms']['sexo_biologico'] ?? null;
        if (!is_array($synonyms)) {
            return null;
        }

        foreach ($synonyms as $code => $variants) {
            $intCode = (int) $code;
            if ($intCode <= 0) {
                continue;
            }
            if ((string) $code === $mention || (string) $intCode === $mention) {
                return $intCode;
            }
            if (!is_array($variants)) {
                continue;
            }
            foreach ($variants as $variant) {
                $v = mb_strtolower(trim((string) $variant), 'UTF-8');
                if ($v !== '' && ($v === $mention || mb_strpos($mention, $v) !== false)) {
                    return $intCode;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string> clave Entidad.grupo => etiqueta para formularios
     */
    public function listEntityGroupOptions(): array
    {
        $out = [];
        $entities = self::load()['entities'] ?? [];
        if (!is_array($entities)) {
            return $out;
        }
        foreach ($entities as $entityName => $groups) {
            if (!is_string($entityName) || !is_array($groups)) {
                continue;
            }
            foreach ($groups as $groupKey => $def) {
                if (!is_string($groupKey)) {
                    continue;
                }
                $fullKey = $entityName . '.' . $groupKey;
                $attrs = is_array($def) ? ($def['attributes'] ?? []) : [];
                $attrList = is_array($attrs) ? implode(', ', array_map('strval', $attrs)) : '';
                $out[$fullKey] = $attrList !== '' ? ($fullKey . ' (' . $attrList . ')') : $fullKey;
            }
        }
        ksort($out);

        return $out;
    }

    public function entityGroupExists(string $entityGroupKey): bool
    {
        return isset($this->listEntityGroupOptions()[trim($entityGroupKey)]);
    }

    /**
     * @return list<string>
     */
    public function getEntityGroupAttributes(string $entityGroupKey): array
    {
        $entityGroupKey = trim($entityGroupKey);
        $dot = strpos($entityGroupKey, '.');
        if ($dot === false) {
            return [];
        }

        $entityName = substr($entityGroupKey, 0, $dot);
        $groupKey = substr($entityGroupKey, $dot + 1);
        $entities = self::load()['entities'] ?? [];
        if (!is_array($entities) || !isset($entities[$entityName]) || !is_array($entities[$entityName])) {
            return [];
        }

        $group = $entities[$entityName][$groupKey] ?? null;
        if (!is_array($group)) {
            return [];
        }

        $attrs = $group['attributes'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($a): string => trim((string) $a),
            $attrs
        ), static fn (string $a): bool => $a !== ''));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listEntitiesForDisplay(): array
    {
        $entities = self::load()['entities'] ?? [];

        return is_array($entities) ? $entities : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listMetricsForDisplay(): array
    {
        $metrics = self::load()['metrics'] ?? [];

        return is_array($metrics) ? $metrics : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEditSurface(string $surfaceId): ?array
    {
        $surfaceId = trim($surfaceId);
        $surfaces = self::load()['edit_surfaces'] ?? [];
        if (!is_array($surfaces) || !isset($surfaces[$surfaceId]) || !is_array($surfaces[$surfaceId])) {
            return null;
        }

        return $surfaces[$surfaceId];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listEditSurfacesForDisplay(): array
    {
        $surfaces = self::load()['edit_surfaces'] ?? [];

        return is_array($surfaces) ? $surfaces : [];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * Grants de referencia en YAML (BD puede override).
     *
     * @return array<string, array<string, mixed>>
     */
    public function listYamlRoleGrants(): array
    {
        $grants = self::load()['role_grants'] ?? [];

        return is_array($grants) ? $grants : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = __DIR__ . '/metadata/' . self::FILE;
        if (!is_file($path)) {
            throw new \RuntimeException('Catálogo DataAccess no encontrado: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Catálogo DataAccess inválido.');
        }

        self::$cache = $data;

        return self::$cache;
    }
}
