<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Resultado de autorizar una consulta: spec + scope aplicado.
 */
final class AuthorizedQuery
{
    /** @var QuerySpec */
    public $spec;

    /** @var ScopeConstraint */
    public $scope;

    /** @var string */
    public $metricId;

    public function __construct(QuerySpec $spec, ScopeConstraint $scope, string $metricId)
    {
        $this->spec = $spec;
        $this->scope = $scope;
        $this->metricId = $metricId;
    }
}
