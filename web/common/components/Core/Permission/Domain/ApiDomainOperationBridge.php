<?php

namespace common\components\Core\Permission\Domain;

use yii\web\ForbiddenHttpException;

/**
 * Puente API: traduce excepciones de dominio a HTTP 403.
 */
final class ApiDomainOperationBridge
{
    /**
     * @param object|array<string, mixed>|null $resource
     * @param array<string, mixed> $params
     */
    public static function assertOrForbidden(string $operationKey, $resource = null, array $params = []): void
    {
        try {
            $ctx = DomainOperationContext::fromApplication($params);
            (new DomainOperationAuthorizer())->assert($operationKey, $resource, $ctx);
        } catch (DomainOperationForbiddenException $e) {
            throw new ForbiddenHttpException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.');
        }
    }
}
