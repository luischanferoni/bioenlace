<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Core\Permission\Validation\CatalogIntegrityService;

class CatalogIntegrityDomainPoliciesTest extends Unit
{
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
