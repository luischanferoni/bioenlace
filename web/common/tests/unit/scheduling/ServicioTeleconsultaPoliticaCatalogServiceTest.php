<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ServicioTeleconsultaPoliticaCatalogService;

class ServicioTeleconsultaPoliticaCatalogServiceTest extends Unit
{
    protected function _before(): void
    {
        ServicioTeleconsultaPoliticaCatalogService::resetCache();
    }

    public function testOpcionesPoliticaIncluyenTresValores(): void
    {
        $opts = (new ServicioTeleconsultaPoliticaCatalogService())->opcionesPolitica();
        $values = array_column($opts, 'value');
        $this->assertContains('NINGUNA', $values);
        $this->assertContains('TODAS', $values);
        $this->assertContains('ALGUNAS', $values);
    }

    public function testKpiEfectorTieneTitulo(): void
    {
        $kpi = (new ServicioTeleconsultaPoliticaCatalogService())->kpiEfector();
        $this->assertNotSame('', $kpi['title']);
        $this->assertNotSame('', $kpi['label_presencial_remoto']);
    }
}
