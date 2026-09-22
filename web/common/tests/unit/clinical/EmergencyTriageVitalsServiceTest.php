<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\Service\EmergencyTriageVitalsService;

final class EmergencyTriageVitalsServiceTest extends Unit
{
    public function testEmptyIsNull(): void
    {
        $this->assertNull(EmergencyTriageVitalsService::normalize(null));
        $this->assertNull(EmergencyTriageVitalsService::normalize([]));
    }

    public function testValidVitals(): void
    {
        $out = EmergencyTriageVitalsService::normalize([
            'bp_sys' => '120',
            'bp_dia' => '80',
            'hr' => '72',
        ]);
        $this->assertSame(['bp_sys' => 120, 'bp_dia' => 80, 'hr' => 72], $out);
    }

    public function testRejectsNonDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmergencyTriageVitalsService::normalize(['bp_sys' => '12a']);
    }

    public function testRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmergencyTriageVitalsService::normalize(['hr' => '15']);
    }

    public function testRejectsSysLeDia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmergencyTriageVitalsService::normalize([
            'bp_sys' => '80',
            'bp_dia' => '90',
        ]);
    }

    public function testFromBodyFlatFields(): void
    {
        $out = EmergencyTriageVitalsService::normalizeFromBody([
            'bp_sys' => '110',
            'hr' => '88',
        ]);
        $this->assertSame(['bp_sys' => 110, 'hr' => 88], $out);
    }
}
