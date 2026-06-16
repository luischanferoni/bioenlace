<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\IntentMetricIndex;

/**
 * Autoriza métricas staff: scope de métrica + permiso intent enlazado.
 */
final class QueryAuthorizationService
{
    /** @var AttributeGroupCatalog */
    private $catalog;

    public function __construct(?AttributeGroupCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
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

        if (!$this->authorizeByBoundIntent($ctx, $spec->metricId)) {
            throw new \InvalidArgumentException(
                'Métrica sin intent autorizado para el usuario: ' . $spec->metricId . '.'
            );
        }

        return $this->authorizeFiltersAndReturn($spec, $ctx, $metric, $scope);
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function authorizeFiltersAndReturn(
        QuerySpec $spec,
        PermissionContext $ctx,
        array $metric,
        ScopeConstraint $scope
    ): AuthorizedQuery {
        $filterGroupMap = $this->catalog->filterEntityGroupMap($spec->metricId);
        $optionalGroups = is_array($metric['optional_filter_groups'] ?? null)
            ? $metric['optional_filter_groups']
            : [];
        $filterScopeCheckers = is_array($metric['filter_scope_checkers'] ?? null)
            ? $metric['filter_scope_checkers']
            : [];

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

            $filterScopeId = trim((string) ($filterScopeCheckers[$groupKey] ?? ''));
            if ($filterScopeId === '') {
                $filterScopeId = trim((string) ($this->catalog->getEntityGroupScopeChecker($groupKey) ?? ''));
            }
            if ($filterScopeId !== '') {
                $filterScope = ScopeCheckerRegistry::get($filterScopeId)->assertAndResolve($spec, $ctx);
                $scope = self::mergeScope($scope, $filterScope);
            }
        }

        QueryAuditLogger::logAuthorized($spec, $ctx, $scope);

        return new AuthorizedQuery($spec, $scope, $spec->metricId);
    }

    private function authorizeByBoundIntent(PermissionContext $ctx, string $metricId): bool
    {
        $intentId = IntentMetricIndex::intentForMetric($metricId);
        if ($intentId === null) {
            return false;
        }

        return BioenlaceAccessChecker::userCanPermissionKey($ctx->userId, $intentId);
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
}
