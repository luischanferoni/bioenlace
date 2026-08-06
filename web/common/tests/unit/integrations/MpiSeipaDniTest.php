<?php

namespace common\tests\unit\integrations;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Mpi\MpiSeipaDni;

class MpiSeipaDniTest extends Unit
{
    public function testRejectsLetterPrefixedDocumento(): void
    {
        $this->assertNull(MpiSeipaDni::toLongQueryParam('X8673354'));
        $this->assertNull(MpiSeipaDni::toLongQueryParam('x8673354'));
    }

    public function testAcceptsNumericDni(): void
    {
        $this->assertSame('30123456', MpiSeipaDni::toLongQueryParam('30123456'));
        $this->assertSame('1234567', MpiSeipaDni::toLongQueryParam('01234567'));
    }

    public function testRejectsEmptyAndTooShort(): void
    {
        $this->assertNull(MpiSeipaDni::toLongQueryParam(''));
        $this->assertNull(MpiSeipaDni::toLongQueryParam('123456'));
        $this->assertNull(MpiSeipaDni::toLongQueryParam(null));
    }
}
