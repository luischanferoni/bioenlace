<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Util\CuilValidator;

class CuilValidatorTest extends Unit
{
    public function testValidKnownCuil(): void
    {
        $this->assertTrue(CuilValidator::isValid('20399998639'));
    }

    public function testBuildFromDniMatchesKnown(): void
    {
        $this->assertSame('20399998639', CuilValidator::buildFromDni('39999863'));
    }

    public function testNormalizeStripsFormatting(): void
    {
        $this->assertSame('20399998639', CuilValidator::normalize('20-39999863-9'));
    }

    public function testRejectInvalidCheckDigit(): void
    {
        $this->assertFalse(CuilValidator::isValid('20399998630'));
    }
}
