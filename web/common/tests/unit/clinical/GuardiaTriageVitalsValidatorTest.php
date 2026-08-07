<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Service\GuardiaTriageVitalsValidator;

final class GuardiaTriageVitalsValidatorTest extends Unit
{
    public function testEmptyIsNull(): void
    {
        $this->assertNull(GuardiaTriageVitalsValidator::normalize(null));
        $this->assertNull(GuardiaTriageVitalsValidator::normalize([]));
    }

    public function testValidVitals(): void
    {
        $out = GuardiaTriageVitalsValidator::normalize([
            'bp_sys' => '120',
            'bp_dia' => '80',
            'hr' => '72',
        ]);
        $this->assertSame(['bp_sys' => 120, 'bp_dia' => 80, 'hr' => 72], $out);
    }

    public function testRejectsNonDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsValidator::normalize(['bp_sys' => '12a']);
    }

    public function testRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsValidator::normalize(['hr' => '15']);
    }

    public function testRejectsSysLeDia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsValidator::normalize([
            'bp_sys' => '80',
            'bp_dia' => '90',
        ]);
    }

    public function testFromBodyFlatFields(): void
    {
        $out = GuardiaTriageVitalsValidator::normalizeFromBody([
            'bp_sys' => '110',
            'hr' => '88',
        ]);
        $this->assertSame(['bp_sys' => 110, 'hr' => 88], $out);
    }
}
