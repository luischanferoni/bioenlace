<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Core\Permission\Validation\CatalogIntegrityService;

class CatalogIntegrityDomainPoliciesTest extends Unit
{
    public function testDomainOperationsHaveCatalogOrDomainOnlyMarker(): void
    {
        $result = (new CatalogIntegrityService())->run();
        $coverageWarnings = array_values(array_filter(
            $result['warnings'],
            static fn (string $msg): bool => str_contains($msg, 'domain-operation-policies')
                && str_contains($msg, 'domain_only_operations')
        ));

        $this->assertEmpty(
            $coverageWarnings,
            "Operaciones sin catálogo ni domain_only:\n" . implode("\n", $coverageWarnings)
        );
    }

    public function testDomainOperationPolicyHandlersAreRegistered(): void
    {
        $result = (new CatalogIntegrityService())->run();
        $handlerErrors = array_values(array_filter(
            $result['errors'],
            static fn (string $msg): bool => str_contains($msg, 'domain-operation-policies')
        ));

        $this->assertEmpty(
            $handlerErrors,
            "Handlers YAML sin registrar:\n" . implode("\n", $handlerErrors)
        );
    }
}
