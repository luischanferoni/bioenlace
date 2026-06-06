<?php

namespace common\components\Core\DataAccess;

/**
 * Orquesta autorización + compilación + ejecución de métricas staff.
 */
final class MetricQueryExecutor
{
    /** @var QueryAuthorizationService */
    private $authorization;

    /** @var QueryCompiler */
    private $compiler;

    public function __construct(
        ?QueryAuthorizationService $authorization = null,
        ?QueryCompiler $compiler = null
    ) {
        $catalog = new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new QueryAuthorizationService($catalog);
        $this->compiler = $compiler ?? new QueryCompiler($catalog);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function executeFromParams(array $params, ?PermissionContext $ctx = null): MetricExecutionResult
    {
        $ctx = $ctx ?? PermissionContext::fromCurrentUser();
        $metricId = trim((string) ($params['metric_id'] ?? $params['metricId'] ?? ''));
        if ($metricId === '') {
            throw new \InvalidArgumentException('metric_id es requerido.');
        }

        $spec = QuerySpec::fromParams($metricId, $params);

        try {
            $authorized = $this->authorization->authorize($spec, $ctx);
        } catch (\InvalidArgumentException $e) {
            QueryAuditLogger::logDenied($spec->metricId, $ctx, $e->getMessage());
            throw $e;
        }

        $result = $this->compiler->execute($authorized, $ctx);
        QueryAuditLogger::logExecuted($result, $ctx);

        return $result;
    }
}
