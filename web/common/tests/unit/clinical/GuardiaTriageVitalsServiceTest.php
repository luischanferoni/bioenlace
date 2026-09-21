<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Service\GuardiaTriageVitalsService;

final class GuardiaTriageVitalsServiceTest extends Unit
{
    public function testEmptyIsNull(): void
    {
        $this->assertNull(GuardiaTriageVitalsService::normalize(null));
        $this->assertNull(GuardiaTriageVitalsService::normalize([]));
    }

    public function testValidVitals(): void
    {
        $out = GuardiaTriageVitalsService::normalize([
            'bp_sys' => '120',
            'bp_dia' => '80',
            'hr' => '72',
        ]);
        $this->assertSame(['bp_sys' => 120, 'bp_dia' => 80, 'hr' => 72], $out);
    }

    public function testRejectsNonDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsService::normalize(['bp_sys' => '12a']);
    }

    public function testRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsService::normalize(['hr' => '15']);
    }

    public function testRejectsSysLeDia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GuardiaTriageVitalsService::normalize([
            'bp_sys' => '80',
            'bp_dia' => '90',
        ]);
    }

    public function testFromBodyFlatFields(): void
    {
        $out = GuardiaTriageVitalsService::normalizeFromBody([
            'bp_sys' => '110',
            'hr' => '88',
        ]);
        $this->assertSame(['bp_sys' => 110, 'hr' => 88], $out);
    }
}
