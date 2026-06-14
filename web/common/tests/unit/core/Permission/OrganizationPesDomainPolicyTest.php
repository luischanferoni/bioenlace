<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\Domain\DomainOperationContext;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Platform\Core\Permission\Domain\DomainOperationPolicyRegistry;
use common\components\Domain\Organization\Service\Authorization\OrganizationPesOwnPolicy;
use common\models\ProfesionalEfectorServicio;

class OrganizationPesDomainPolicyTest extends Unit
{
    protected function _after(): void
    {
        DomainOperationPolicyRegistry::resetForTests();
    }

    public function testPesOwnAllowsMatchingPersona(): void
    {
        $pes = new ProfesionalEfectorServicio();
        $pes->id = 10;
        $pes->id_persona = 42;
        $pes->deleted_at = null;

        $ctx = new DomainOperationContext(1, 42, 5, false, []);

        (new OrganizationPesOwnPolicy())->assert($ctx, $pes);
        $this->assertTrue(true);
    }

    public function testPesOwnRejectsOtherPersona(): void
    {
        $pes = new ProfesionalEfectorServicio();
        $pes->id = 10;
        $pes->id_persona = 99;
        $pes->deleted_at = null;

        $ctx = new DomainOperationContext(1, 42, 5, false, []);

        $this->expectException(DomainOperationForbiddenException::class);
        (new OrganizationPesOwnPolicy())->assert($ctx, $pes);
    }

    public function testPesOwnSkipsCheckForSuperadmin(): void
    {
        $pes = new ProfesionalEfectorServicio();
        $pes->id = 10;
        $pes->id_persona = 99;
        $pes->deleted_at = null;

        $ctx = new DomainOperationContext(1, 42, 5, true, []);

        (new OrganizationPesOwnPolicy())->assert($ctx, $pes);
        $this->assertTrue(true);
    }
}
