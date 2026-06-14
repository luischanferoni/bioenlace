<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Autoriza métricas staff: scope de métrica + permisos por grupo de atributos en filtros.
 */
final class QueryAuthorizationService
{
    /** @var AttributeGroupCatalog */
    private $catalog;

    /** @var AttributePermissionEvaluator */
    private $permissions;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?AttributePermissionEvaluator $permissions = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->permissions = $permissions ?? new AttributePermissionEvaluator();
    }

    /**
     * @throws \InvalidArgumentException si la métrica o permisos no aplican
     */
    public function authorize(QuerySpec $spec, PermissionContext $ctx): AuthorizedQuery
    {
        $metric = $this->catalog->getMetric($spec->metricId);
        if ($metric === null) {
            throw new \InvalidArgumentException('Métrica no registrada: ' . $spec->metricId);
        }

        $metricScopeId = trim((string) ($metric['scope_checker'] ?? ''));
        if ($metricScopeId === '') {
            throw new \InvalidArgumentException('Métrica sin scope_checker: ' . $spec->metricId);
        }

        $scope = ScopeCheckerRegistry::get($metricScopeId)->assertAndResolve($spec, $ctx);

        $requiredGroups = $metric['required_groups'] ?? [];
        if (is_array($requiredGroups)) {
            foreach ($requiredGroups as $groupKey) {
                $groupKey = trim((string) $groupKey);
                if ($groupKey === '') {
                    continue;
                }
                $op = $this->requiredOperationForSpec($spec, $metric);
                if (!$this->permissions->can($ctx, $groupKey, $op)) {
                    throw new \InvalidArgumentException(
                        'No tiene permiso para consultar la métrica ' . $spec->metricId . '.'
                    );
                }
            }
        }

        $readGroups = $metric['read_groups'] ?? [];
        if (is_array($readGroups) && $this->isRowsOutput($spec, $metric)) {
            $readable = false;
            foreach ($readGroups as $groupKey) {
                $groupKey = trim((string) $groupKey);
                if ($groupKey === '') {
                    continue;
                }
                if ($this->permissions->can($ctx, $groupKey, QueryOperation::READ)) {
                    $readable = true;
                    break;
                }
            }
            if (!$readable) {
                throw new \InvalidArgumentException(
                    'No tiene permiso de lectura para listar ' . $spec->metricId . '.'
                );
            }
        }

        $optionalGroups = $metric['optional_filter_groups'] ?? [];
        $filterScopeCheckers = is_array($metric['filter_scope_checkers'] ?? null)
            ? $metric['filter_scope_checkers']
            : [];
        $filterGroupMap = $this->catalog->filterEntityGroupMap($spec->metricId);

        foreach ($spec->filters as $filterKey => $filterValue) {
            if ($filterValue === null || $filterValue === '') {
                continue;
            }
            if (!isset($filterGroupMap[$filterKey])) {
                continue;
            }
            $groupKey = $filterGroupMap[$filterKey];
            if (is_array($optionalGroups) && $optionalGroups !== [] && !in_array($groupKey, $optionalGroups, true)) {
                throw new \InvalidArgumentException('Filtro no permitido para esta métrica: ' . $filterKey);
            }
            if (!$this->permissions->can($ctx, $groupKey, QueryOperation::FILTER)) {
                throw new \InvalidArgumentException(
                    'No tiene permiso para filtrar por ' . $filterKey . '.'
                );
            }

            $filterScopeId = trim((string) ($filterScopeCheckers[$groupKey] ?? ''));
            if ($filterScopeId === '') {
                $filterScopeId = trim((string) ($this->permissions->scopeCheckerFor($ctx, $groupKey) ?? ''));
            }
            if ($filterScopeId !== '') {
                $filterScope = ScopeCheckerRegistry::get($filterScopeId)->assertAndResolve($spec, $ctx);
                $scope = self::mergeScope($scope, $filterScope);
            }
        }

        QueryAuditLogger::logAuthorized($spec, $ctx, $scope);

        return new AuthorizedQuery($spec, $scope, $spec->metricId);
    }

    private static function mergeScope(ScopeConstraint $base, ScopeConstraint $extra): ScopeConstraint
    {
        if ($extra->idEfector !== null && $extra->idEfector > 0) {
            if ($base->idEfector !== null && $base->idEfector > 0 && $base->idEfector !== $extra->idEfector) {
                throw new \InvalidArgumentException('Scope de efector inconsistente.');
            }
            $base->idEfector = $extra->idEfector;
        }
        if ($extra->idPersona !== null && $extra->idPersona > 0) {
            if ($base->idPersona !== null && $base->idPersona > 0 && $base->idPersona !== $extra->idPersona) {
                throw new \InvalidArgumentException('Scope de persona inconsistente.');
            }
            $base->idPersona = $extra->idPersona;
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function requiredOperationForSpec(QuerySpec $spec, array $metric): string
    {
        if ($spec->outputMode === QueryOutputMode::ROWS) {
            return QueryOperation::READ;
        }
        $plan = $metric['query']['output']['default'] ?? null;
        if ($spec->outputMode === null && $plan === QueryOutputMode::ROWS) {
            return QueryOperation::READ;
        }

        return QueryOperation::AGGREGATE;
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function isRowsOutput(QuerySpec $spec, array $metric): bool
    {
        if ($spec->outputMode === QueryOutputMode::ROWS) {
            return true;
        }
        if ($spec->outputMode !== null) {
            return false;
        }
        $query = isset($metric['query']) && is_array($metric['query']) ? $metric['query'] : [];
        $output = isset($query['output']) && is_array($query['output']) ? $query['output'] : [];

        return QueryOutputMode::normalize((string) ($output['default'] ?? '')) === QueryOutputMode::ROWS;
    }
}
