<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaCatalogService;

class ConsultaAsyncBandejaCatalogServiceTest extends Unit
{
    protected function _before(): void
    {
        ConsultaAsyncBandejaCatalogService::resetCache();
    }

    public function testSlaHorasPorBandaUrgencia(): void
    {
        $svc = new ConsultaAsyncBandejaCatalogService();
        $this->assertSame(4, $svc->horasSlaRespuesta('A'));
        $this->assertSame(24, $svc->horasSlaRespuesta('C'));
        $this->assertSame(48, $svc->horasSlaRespuesta(null);
    }

    public function testEtiquetasEstado(): void
    {
        $svc = new ConsultaAsyncBandejaCatalogService();
        $this->assertSame('Pendiente de respuesta', $svc->etiquetaEstado('planned'));
        $this->assertSame('En curso', $svc->etiquetaEstado('in-progress'));
    }
}
