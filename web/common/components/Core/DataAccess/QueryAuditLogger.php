<?php

namespace common\components\Core\DataAccess;

use Yii;

/**
 * Auditoría de consultas staff autorizadas.
 */
final class QueryAuditLogger
{
    public static function logAuthorized(QuerySpec $spec, PermissionContext $ctx, ScopeConstraint $scope): void
    {
        Yii::info([
            'event' => 'data_access_query_authorized',
            'metric_id' => $spec->metricId,
            'user_id' => $ctx->userId,
            'roles' => $ctx->roleNames,
            'filters' => $spec->filters,
            'scope' => $scope->toLogContext(),
        ], 'data-access');
    }

    public static function logDenied(string $metricId, PermissionContext $ctx, string $reason): void
    {
        Yii::warning([
            'event' => 'data_access_query_denied',
            'metric_id' => $metricId,
            'user_id' => $ctx->userId,
            'roles' => $ctx->roleNames,
            'reason' => $reason,
        ], 'data-access');
    }

    public static function logExecuted(MetricExecutionResult $result, PermissionContext $ctx): void
    {
        Yii::info([
            'event' => 'data_access_query_executed',
            'metric_id' => $result->metricId,
            'output_mode' => $result->outputMode,
            'user_id' => $ctx->userId,
            'roles' => $ctx->roleNames,
            'aggregates' => $result->aggregates,
            'row_count' => count($result->rows),
            'group_count' => count($result->groups),
            'resolved_filters' => $result->resolvedFilters,
            'short_circuit_empty' => $result->shortCircuitEmpty,
        ], 'data-access');
    }
}
