<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\DataAccess\Filter\FilterResolvedValue;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverContext;
use common\components\Platform\Core\DataAccess\Filter\FilterValueResolverRegistry;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * Compila y ejecuta métricas declarativas: agregados, filas (read) y group_by.
 */
final class QueryCompiler
{
    /** @var AttributeGroupCatalog */
    private $catalog;

    /** @var QueryProjectionBuilder */
    private $projection;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?QueryProjectionBuilder $projection = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->projection = $projection ?? new QueryProjectionBuilder();
    }

    public function execute(AuthorizedQuery $authorized, PermissionContext $ctx): MetricExecutionResult
    {
        $metricId = $authorized->metricId;
        $plan = $this->catalog->getMetricQueryPlan($metricId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Métrica sin plan query: ' . $metricId);
        }

        $outputMode = $this->resolveOutputMode($authorized->spec, $plan);
        $built = $this->buildFilteredQuery($authorized, $plan, $outputMode);
        $metaBase = ['id_efector' => (int) ($authorized->scope->idEfector ?? 0)];

        if ($built['short_circuit']) {
            return $this->emptyResult($metricId, $outputMode, $built['resolved_meta'], $metaBase, true);
        }

        if ($outputMode === QueryOutputMode::ROWS) {
            return $this->executeRows($authorized, $plan, $built['query'], $ctx, $built['resolved_meta'], $metaBase);
        }

        if ($outputMode === QueryOutputMode::GROUPED) {
            return $this->executeGrouped($metricId, $plan, $authorized->spec, $built['query'], $built['resolved_meta'], $metaBase);
        }

        return $this->executeAggregate($metricId, $plan, $authorized->spec, $built['query'], $built['resolved_meta'], $metaBase);
    }

    /**
     * @return array{query: ActiveQuery, resolved_meta: array<string, mixed>, short_circuit: bool}
     */
    private function buildFilteredQuery(AuthorizedQuery $authorized, array $plan, string $outputMode): array
    {
        $workingFilters = $this->normalizeFilters($authorized, $plan);
        $resolvedMeta = [];
        $shortCircuit = false;

        $rootDef = isset($plan['root']) && is_array($plan['root']) ? $plan['root'] : null;
        if ($rootDef === null) {
            throw new \InvalidArgumentException('Plan query sin root.');
        }

        $query = $this->createRootQuery($rootDef);
        $this->applyScope($query, $plan, $authorized->scope);
        $this->applyBaseWhere($query, $plan);

        $joinsApplied = [];
        if ($outputMode === QueryOutputMode::ROWS) {
            $output = isset($plan['output']) && is_array($plan['output']) ? $plan['output'] : [];
            $rowsDef = isset($output['rows']) && is_array($output['rows']) ? $output['rows'] : [];
            $staticJoins = isset($rowsDef['static_joins']) && is_array($rowsDef['static_joins'])
                ? $rowsDef['static_joins']
                : [];
            foreach ($staticJoins as $joinKey) {
                $this->applyJoinIfNeeded($query, $plan, trim((string) $joinKey), $joinsApplied);
            }
        }

        if ($outputMode === QueryOutputMode::GROUPED) {
            $this->applyJoinIfNeeded($query, $plan, 'servicio', $joinsApplied);
        }

        $filterDefs = isset($plan['filters']) && is_array($plan['filters']) ? $plan['filters'] : [];
        foreach ($filterDefs as $filterKey => $filterDef) {
            if (!is_array($filterDef)) {
                continue;
            }
            $filterKey = trim((string) $filterKey);
            if ($filterKey === '' || !array_key_exists($filterKey, $workingFilters)) {
                continue;
            }
            $raw = $workingFilters[$filterKey];
            if ($raw === null || $raw === '') {
                continue;
            }

            $resolverId = trim((string) ($filterDef['resolver'] ?? ''));
            if ($resolverId === '' || (isset($filterDef['normalize_to']) && !isset($filterDef['apply_column']))) {
                continue;
            }

            $ctx = new FilterValueResolverContext(
                $authorized,
                $this->catalog,
                $filterKey,
                $raw,
                $workingFilters
            );
            $resolved = FilterValueResolverRegistry::get($resolverId)->resolve($ctx, $filterDef);
            $resolvedMeta = array_merge($resolvedMeta, $resolved->meta);

            if ($resolved->shortCircuitEmpty) {
                return ['query' => $query, 'resolved_meta' => $resolvedMeta, 'short_circuit' => true];
            }
            if (!$resolved->apply) {
                continue;
            }

            $requiresJoin = trim((string) ($filterDef['requires_join'] ?? ''));
            if ($requiresJoin !== '') {
                $this->applyJoinIfNeeded($query, $plan, $requiresJoin, $joinsApplied);
            }

            $this->applyFilterCondition($query, $resolved);
        }

        return ['query' => $query, 'resolved_meta' => $resolvedMeta, 'short_circuit' => false];
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $resolvedMeta
     * @param array<string, mixed> $metaBase
     */
    private function executeRows(
        AuthorizedQuery $authorized,
        array $plan,
        ActiveQuery $query,
        PermissionContext $ctx,
        array $resolvedMeta,
        array $metaBase
    ): MetricExecutionResult {
        $output = isset($plan['output']) && is_array($plan['output']) ? $plan['output'] : [];
        $rowsDef = isset($output['rows']) && is_array($output['rows']) ? $output['rows'] : [];
        $fields = $this->projection->resolveReadableFields($ctx, $rowsDef);

        $select = [];
        foreach ($fields as $field) {
            $select[$field['output_key']] = new Expression($field['sql_expression']);
        }

        $maxLimit = (int) ($rowsDef['max_limit'] ?? 200);
        if ($maxLimit < 1) {
            $maxLimit = 200;
        }
        if ($maxLimit > 500) {
            $maxLimit = 500;
        }
        $limit = $authorized->spec->limit ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $fetchLimit = min(500, max($limit * 5, $limit));
        $query->select($select)->limit($fetchLimit);

        $orderBy = isset($rowsDef['order_by']) && is_array($rowsDef['order_by']) ? $rowsDef['order_by'] : [];
        $normalizedOrder = $this->normalizeOrderBy($orderBy);
        if ($normalizedOrder !== []) {
            $query->orderBy($normalizedOrder);
        }

        $rawRows = $query->asArray()->all();
        $distinctColumn = trim((string) ($rowsDef['distinct_column'] ?? ''));
        $rows = $this->dedupeRows($rawRows, $distinctColumn, $fields, $limit);

        return new MetricExecutionResult(
            $authorized->metricId,
            QueryOutputMode::ROWS,
            [],
            $rows,
            [],
            $resolvedMeta,
            false,
            array_merge($metaBase, ['row_count' => count($rows)])
        );
    }

    /**
     * @param list<array<string, mixed>> $rawRows
     * @param list<array{output_key: string, sql_expression: string, entity_group: string}> $fields
     * @return list<array<string, mixed>>
     */
    private function dedupeRows(array $rawRows, string $distinctColumn, array $fields, int $limit): array
    {
        $distinctKey = '';
        if ($distinctColumn !== '') {
            foreach ($fields as $field) {
                if ($field['sql_expression'] === $distinctColumn) {
                    $distinctKey = $field['output_key'];
                    break;
                }
            }
            if ($distinctKey === '') {
                $parts = explode('.', $distinctColumn);
                $distinctKey = end($parts) ?: $distinctColumn;
            }
        }

        $seen = [];
        $out = [];
        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dedupe = $distinctKey !== '' ? (string) ($row[$distinctKey] ?? '') : md5(json_encode($row));
            if ($dedupe === '' || isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $resolvedMeta
     * @param array<string, mixed> $metaBase
     */
    private function executeGrouped(
        string $metricId,
        array $plan,
        QuerySpec $spec,
        ActiveQuery $query,
        array $resolvedMeta,
        array $metaBase
    ): MetricExecutionResult {
        $aggDef = $this->resolveAggregationDefinition($plan, $spec);
        $groupBy = isset($aggDef['group_by']) && is_array($aggDef['group_by']) ? $aggDef['group_by'] : [];
        if ($groupBy === []) {
            throw new \InvalidArgumentException('Agregación grouped sin group_by.');
        }

        $countColumn = trim((string) ($aggDef['column'] ?? ''));
        if ($countColumn === '') {
            throw new \InvalidArgumentException('Agregación grouped sin column.');
        }

        $select = [];
        foreach ($groupBy as $col) {
            $select[] = $col;
        }
        $select['count'] = new Expression('COUNT(DISTINCT ' . $countColumn . ')');

        $rows = $query->select($select)->groupBy($groupBy)->orderBy($this->normalizeOrderBy($groupBy))->asArray()->all();
        $resultKeys = isset($aggDef['result_keys']) && is_array($aggDef['result_keys']) ? $aggDef['result_keys'] : [];
        $groups = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = ['total' => (int) ($row['count'] ?? 0)];
            foreach ($resultKeys as $outKey => $sourceCol) {
                $sourceCol = (string) $sourceCol;
                $parts = explode('.', $sourceCol);
                $alias = end($parts);
                $item[$outKey] = $row[$alias] ?? $row[$sourceCol] ?? null;
            }
            $groups[] = $item;
        }

        $total = 0;
        foreach ($groups as $g) {
            $total += (int) ($g['total'] ?? 0);
        }

        return new MetricExecutionResult(
            $metricId,
            QueryOutputMode::GROUPED,
            [$this->aggregationKey($aggDef) => $total],
            [],
            $groups,
            $resolvedMeta,
            false,
            array_merge($metaBase, ['group_count' => count($groups)])
        );
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $resolvedMeta
     * @param array<string, mixed> $metaBase
     */
    private function executeAggregate(
        string $metricId,
        array $plan,
        QuerySpec $spec,
        ActiveQuery $query,
        array $resolvedMeta,
        array $metaBase
    ): MetricExecutionResult {
        $aggDef = $this->resolveAggregationDefinition($plan, $spec);
        $value = $this->runScalarAggregation($query, $aggDef);

        return new MetricExecutionResult(
            $metricId,
            QueryOutputMode::AGGREGATE,
            [$this->aggregationKey($aggDef) => $value],
            [],
            [],
            $resolvedMeta,
            false,
            $metaBase
        );
    }

    /**
     * @param array<string, mixed> $resolvedMeta
     * @param array<string, mixed> $metaBase
     */
    private function emptyResult(
        string $metricId,
        string $outputMode,
        array $resolvedMeta,
        array $metaBase,
        bool $sinServicio
    ): MetricExecutionResult {
        $meta = array_merge($metaBase, ['sin_servicio_en_efector' => $sinServicio]);

        return new MetricExecutionResult(
            $metricId,
            $outputMode,
            $outputMode === QueryOutputMode::AGGREGATE || $outputMode === QueryOutputMode::GROUPED
                ? ['count_distinct_persona' => 0]
                : [],
            $outputMode === QueryOutputMode::ROWS ? [] : [],
            $outputMode === QueryOutputMode::GROUPED ? [] : [],
            $resolvedMeta,
            true,
            $meta
        );
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function resolveOutputMode(QuerySpec $spec, array $plan): string
    {
        if ($spec->outputMode !== null && QueryOutputMode::isValid($spec->outputMode)) {
            $mode = $spec->outputMode;
        } else {
            $output = isset($plan['output']) && is_array($plan['output']) ? $plan['output'] : [];
            $mode = QueryOutputMode::normalize((string) ($output['default'] ?? QueryOutputMode::AGGREGATE));
        }

        $output = isset($plan['output']) && is_array($plan['output']) ? $plan['output'] : [];
        $modes = isset($output['modes']) && is_array($output['modes']) ? $output['modes'] : [];
        if ($modes !== [] && !in_array($mode, $modes, true)) {
            throw new \InvalidArgumentException('output_mode no permitido para esta métrica: ' . $mode);
        }

        return $mode;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFilters(AuthorizedQuery $authorized, array $plan): array
    {
        $filters = $authorized->spec->filters;
        $filterDefs = isset($plan['filters']) && is_array($plan['filters']) ? $plan['filters'] : [];

        foreach ($filterDefs as $filterKey => $filterDef) {
            if (!is_array($filterDef)) {
                continue;
            }
            $normalizeTo = trim((string) ($filterDef['normalize_to'] ?? ''));
            if ($normalizeTo === '') {
                continue;
            }
            if (!array_key_exists($filterKey, $filters) || $filters[$filterKey] === '') {
                continue;
            }
            if (isset($filters[$normalizeTo]) && $filters[$normalizeTo] !== '') {
                continue;
            }

            $resolverId = trim((string) ($filterDef['resolver'] ?? ''));
            if ($resolverId === '') {
                continue;
            }

            $ctx = new FilterValueResolverContext(
                $authorized,
                $this->catalog,
                (string) $filterKey,
                $filters[$filterKey],
                $filters
            );
            $resolved = FilterValueResolverRegistry::get($resolverId)->resolve($ctx, $filterDef);
            if (isset($resolved->meta['servicio_rol']) && $resolved->meta['servicio_rol'] !== '') {
                $filters[$normalizeTo] = $resolved->meta['servicio_rol'];
            }
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $rootDef
     */
    private function createRootQuery(array $rootDef): ActiveQuery
    {
        $modelClass = trim((string) ($rootDef['model'] ?? ''));
        if ($modelClass === '' || !class_exists($modelClass)) {
            throw new \InvalidArgumentException('Modelo root inválido en plan query.');
        }
        $alias = trim((string) ($rootDef['alias'] ?? 't'));
        if ($alias === '') {
            $alias = 't';
        }

        return $modelClass::find()->alias($alias);
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function applyScope(ActiveQuery $query, array $plan, ScopeConstraint $scope): void
    {
        $bindings = isset($plan['scope_columns']) && is_array($plan['scope_columns'])
            ? $plan['scope_columns']
            : [];

        if (isset($bindings['id_efector']) && $scope->idEfector !== null && $scope->idEfector > 0) {
            $query->andWhere([$bindings['id_efector'] => $scope->idEfector]);
        }
        if (isset($bindings['id_persona']) && $scope->idPersona !== null && $scope->idPersona > 0) {
            $query->andWhere([$bindings['id_persona'] => $scope->idPersona]);
        }
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function applyBaseWhere(ActiveQuery $query, array $plan): void
    {
        $baseWhere = isset($plan['base_where']) && is_array($plan['base_where'])
            ? $plan['base_where']
            : [];
        foreach ($baseWhere as $column => $value) {
            $query->andWhere([$column => $value]);
        }
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, bool> $joinsApplied
     */
    private function applyJoinIfNeeded(ActiveQuery $query, array $plan, string $joinKey, array &$joinsApplied): void
    {
        if ($joinKey === '' || isset($joinsApplied[$joinKey])) {
            return;
        }

        $joins = isset($plan['joins']) && is_array($plan['joins']) ? $plan['joins'] : [];
        if (!isset($joins[$joinKey]) || !is_array($joins[$joinKey])) {
            throw new \InvalidArgumentException('Join no declarado: ' . $joinKey);
        }

        $joinDef = $joins[$joinKey];
        $table = trim((string) ($joinDef['table'] ?? ''));
        $alias = trim((string) ($joinDef['alias'] ?? $joinKey));
        $on = trim((string) ($joinDef['on'] ?? ''));
        $type = mb_strtolower(trim((string) ($joinDef['type'] ?? 'inner')), 'UTF-8');

        if ($table === '' || $on === '') {
            throw new \InvalidArgumentException('Join incompleto: ' . $joinKey);
        }

        if ($type === 'left') {
            $query->leftJoin([$alias => $table], $on);
        } else {
            $query->innerJoin([$alias => $table], $on);
        }

        $joinsApplied[$joinKey] = true;
    }

    private function applyFilterCondition(ActiveQuery $query, FilterResolvedValue $resolved): void
    {
        if (!$resolved->apply || $resolved->column === '') {
            return;
        }

        $op = mb_strtolower(trim($resolved->op), 'UTF-8');
        if ($op === 'in') {
            $values = is_array($resolved->value) ? $resolved->value : [$resolved->value];
            $query->andWhere([$resolved->column => $values]);

            return;
        }

        $query->andWhere([$resolved->column => $resolved->value]);
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function resolveAggregationDefinition(array $plan, QuerySpec $spec): array
    {
        $agg = isset($plan['aggregation']) && is_array($plan['aggregation']) ? $plan['aggregation'] : [];
        $requested = $spec->aggregationKey ?? ($spec->aggregations[0] ?? null);
        $defaultKey = trim((string) ($requested ?? $agg['default'] ?? ''));
        $definitions = isset($agg['definitions']) && is_array($agg['definitions']) ? $agg['definitions'] : [];

        if ($defaultKey !== '' && isset($definitions[$defaultKey]) && is_array($definitions[$defaultKey])) {
            $def = $definitions[$defaultKey];
            $def['_key'] = $defaultKey;

            return $def;
        }

        foreach ($definitions as $key => $def) {
            if (is_array($def)) {
                $def['_key'] = (string) $key;

                return $def;
            }
        }

        throw new \InvalidArgumentException('Plan query sin agregación definida.');
    }

    /**
     * @param array<string, mixed> $aggDef
     */
    private function aggregationKey(array $aggDef): string
    {
        return trim((string) ($aggDef['_key'] ?? 'result'));
    }

    /**
     * @param array<string, mixed> $aggDef
     */
    private function runScalarAggregation(ActiveQuery $query, array $aggDef): int
    {
        $type = mb_strtolower(trim((string) ($aggDef['type'] ?? '')), 'UTF-8');
        $column = trim((string) ($aggDef['column'] ?? ''));

        if ($type === 'count_distinct' && $column !== '') {
            $subQuery = (clone $query)->select($column)->distinct();

            return (int) (new Query())->from(['sub' => $subQuery])->count('*');
        }

        throw new \InvalidArgumentException('Tipo de agregación no soportado: ' . $type);
    }

    /**
     * Convierte `order_by` del YAML a formato Yii: `['col' => SORT_ASC]`.
     *
     * Acepta lista (`p.apellido ASC`) o mapa (`p.apellido: ASC`). Evita pasar arrays
     * indexados crudos a {@see ActiveQuery::orderBy()}, que en algunos casos generan `ORDER BY 0, 1`.
     *
     * @param array<mixed> $orderBy
     * @return array<string, int>
     */
    private function normalizeOrderBy(array $orderBy): array
    {
        $out = [];
        foreach ($orderBy as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $column = trim($key);
                if ($column === '') {
                    continue;
                }
                $out[$column] = $this->sortDirection($value);

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $token = trim($value);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s+(ASC|DESC)$/i', $token, $matches) === 1) {
                $out[trim($matches[1])] = $this->sortDirection($matches[2]);

                continue;
            }

            $out[$token] = SORT_ASC;
        }

        return $out;
    }

    /**
     * @param mixed $value
     */
    private function sortDirection($value): int
    {
        if ($value === SORT_ASC || $value === SORT_DESC) {
            return (int) $value;
        }

        return strcasecmp(trim((string) $value), 'DESC') === 0 ? SORT_DESC : SORT_ASC;
    }
}
