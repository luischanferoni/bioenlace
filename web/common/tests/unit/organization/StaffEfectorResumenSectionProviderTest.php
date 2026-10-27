<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Efector\Application\Service\EfectorResumenMetricsService;
use common\components\Domain\Organization\Efector\Application\Presentation\StaffEfectorResumenSectionProvider;

class StaffEfectorResumenSectionProviderTest extends Unit
{
    public function testGuestGetsEmptySection(): void
    {
        $out = (new StaffEfectorResumenSectionProvider())->build([]);

        $this->assertSame(['title' => '', 'items' => []], $out);
    }

    public function testGuestWithEfectorInContextStillGetsEmptySection(): void
    {
        // El gate es el rol operativo, no el id_efector del contexto.
        $out = (new StaffEfectorResumenSectionProvider())->build(['id_efector' => 1]);

        $this->assertSame(['title' => '', 'items' => []], $out);
    }

    public function testMetricsServiceReturnsEmptyForMissingEfector(): void
    {
        $service = new EfectorResumenMetricsService();

        $this->assertSame([], $service->resumen(0));
        $this->assertSame([], $service->resumen(-5));
    }
}
