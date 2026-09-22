<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Domain\Policy\CuilPolicy;

class CuilPolicyTest extends Unit
{
    public function testValidKnownCuil(): void
    {
        $this->assertTrue(CuilPolicy::isValid('20399998639'));
    }

    public function testBuildFromDniMatchesKnown(): void
    {
        $this->assertSame('20399998639', CuilPolicy::buildFromDni('39999863'));
    }

    public function testNormalizeStripsFormatting(): void
    {
        $this->assertSame('20399998639', CuilPolicy::normalize('20-39999863-9'));
    }

    public function testRejectInvalidCheckDigit(): void
    {
        $this->assertFalse(CuilPolicy::isValid('20399998630'));
    }
}
