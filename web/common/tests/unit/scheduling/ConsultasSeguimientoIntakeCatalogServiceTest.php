<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeCatalogService;

class ConsultasSeguimientoIntakeCatalogServiceTest extends Unit
{
    public function testOpcionesTipoIncluyeConsultaGeneralYSeguimiento(): void
    {
        $svc = new ConsultasSeguimientoIntakeCatalogService();
        $codes = array_column($svc->opcionesTipo(), 'code');
        $this->assertContains('consulta_general', $codes);
        $this->assertContains('seguimiento', $codes);
        $this->assertContains('seguimiento_consulta_previa', $codes);
    }

    public function testNecesidadesIncluyenSolicitarTurno(): void
    {
        $svc = new ConsultasSeguimientoIntakeCatalogService();
        $nec = $svc->necesidad('solicitar_turno');
        $this->assertNotNull($nec);
        $this->assertFalse($nec['permite_async']);
    }

    public function testNecesidadesSeparanRenovarYAjuste(): void
    {
        $svc = new ConsultasSeguimientoIntakeCatalogService();
        $codes = array_column($svc->opcionesNecesidad(), 'code');
        $this->assertContains('renovar_medicacion', $codes);
        $this->assertContains('solicitar_ajuste', $codes);

        $renovar = $svc->necesidad('renovar_medicacion');
        $ajuste = $svc->necesidad('solicitar_ajuste');
        $this->assertNotNull($renovar);
        $this->assertNotNull($ajuste);
        $this->assertTrue($renovar['permite_async']);
        $this->assertTrue($ajuste['permite_async']);
        $this->assertSame('', $renovar['composer_placeholder']);
        $this->assertNotSame('', $ajuste['composer_placeholder']);
    }

    public function testAccionesCarePlanTienenIntakeSeguimiento(): void
    {
        $svc = new ConsultasSeguimientoIntakeCatalogService();
        $actions = $svc->accionesSeguimientoCarePlan();
        $this->assertNotEmpty($actions);
        foreach ($actions as $row) {
            $this->assertSame('seguimiento', $row['intake_tipo']);
            $this->assertNotSame('', $row['seguimiento_necesidad']);
        }
    }
}
