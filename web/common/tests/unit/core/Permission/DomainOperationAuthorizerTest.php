<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyCatalog;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyRegistry;
use common\models\Turno;

class DomainOperationAuthorizerTest extends Unit
{
    protected function _after(): void
    {
        DomainOperationPolicyCatalog::resetCacheForTests();
        DomainOperationPolicyRegistry::resetForTests();
    }

    public function testTurnoCancelAllowsStaffEfectorMatch(): void
    {
        $turno = new Turno();
        $turno->id_persona = 999;
        $turno->id_efector = 5;

        $ctx = new DomainOperationContext(1, 100, 5, false, []);

        (new DomainOperationAuthorizer())->assert('Turno.cancel', $turno, $ctx);
        $this->assertTrue(true);
    }

    public function testTurnoCancelRejectsStaffWrongEfector(): void
    {
        $turno = new Turno();
        $turno->id_persona = 999;
        $turno->id_efector = 5;

        $ctx = new DomainOperationContext(1, 100, 7, false, []);

        $this->expectException(DomainOperationForbiddenException::class);
        (new DomainOperationAuthorizer())->assert('Turno.cancel', $turno, $ctx);
    }

    public function testTurnoReprogramarAllowsOwner(): void
    {
        $turno = new Turno();
        $turno->id_persona = 42;
        $turno->id_efector = 1;

        $ctx = new DomainOperationContext(1, 42, null, false, []);

        (new DomainOperationAuthorizer())->assert('Turno.reprogramar', $turno, $ctx);
        $this->assertTrue(true);
    }

    public function testUnknownOperationThrowsForbidden(): void
    {
        $turno = new Turno();
        $this->expectException(DomainOperationForbiddenException::class);
        $this->expectExceptionMessage('Entidad.inexistente');
        (new DomainOperationAuthorizer())->assert('Entidad.inexistente', $turno);
    }
}
