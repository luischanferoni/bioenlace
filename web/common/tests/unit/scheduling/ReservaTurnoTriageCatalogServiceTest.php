<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ReservaTurnoTriageCatalogService;

class ReservaTurnoTriageCatalogServiceTest extends Unit
{
    public function testUrgenciaCategoriaHaltsBooking(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $this->assertTrue($svc->nodeHaltsBooking('urg_cat_cardiaco'));
        $this->assertTrue($svc->nodeHaltsBooking('urg_cat_neurologico'));
    }

    public function testCaminoUrgenciaDetectsHalt(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $compiled = $svc->compileSelections([
            'triage_raiz' => 'urgencia',
            'triage_alarmas' => 'urg_cat_respiratorio',
        ]);
        $this->assertTrue($compiled['reserva_triage_halt']);
        $this->assertSame('A', $compiled['urgency_band']);
    }

    public function testMalestarNuevoRequiereZona(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $compiled = $svc->compileSelections([
            'triage_raiz' => 'malestar_nuevo',
            'triage_zona' => 'zona_pecho',
        ]);
        $this->assertFalse($compiled['reserva_triage_halt']);
        $this->assertSame('zona_pecho', $compiled['reserva_triage_code']);
        $svc->assertCanPersistBooking([
            'triage_raiz' => 'malestar_nuevo',
            'triage_zona' => 'zona_abdomen',
        ]);
    }

    public function testAssertCanPersistRejectsUrgencia(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->assertCanPersistBooking([
            'triage_raiz' => 'urgencia',
            'triage_alarmas' => 'urg_cat_trauma_sangrado',
        ]);
    }

    public function testRaizNoListaSeguimientoCronico(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $codes = array_column($svc->getOptionsForStep('raiz'), 'code');
        $this->assertContains('malestar_nuevo', $codes);
        $this->assertContains('urgencia', $codes);
        $this->assertNotContains('seguimiento_cronico', $codes);
        $this->assertNotNull($svc->findNode('seguimiento_cronico'));
    }
}
