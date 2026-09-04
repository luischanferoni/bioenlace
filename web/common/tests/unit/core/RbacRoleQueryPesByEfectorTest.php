<?php

namespace common\tests\unit\core;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\RbacRoleQueryService;

class RbacRoleQueryPesByEfectorTest extends Unit
{
    public function testInvalidUserReturnsEmpty(): void
    {
        $this->assertSame([], RbacRoleQueryService::listPesRolesByEfectorForUser(0));
        $this->assertSame([], RbacRoleQueryService::listPesRolesByEfectorForUser(-1));
    }
}
