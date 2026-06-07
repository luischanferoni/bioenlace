<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Scheduling\Service\ReservaTurnoTriageCatalogService;

class ReservaTurnoTriageCatalogServiceTest extends Unit
{
    public function testAlarmaBandAHaltsBooking(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $this->assertTrue($svc->nodeHaltsBooking('alarma_grupo_pecho_respiracion'));
        $this->assertFalse($svc->nodeHaltsBooking('alarma_ninguna'));
    }

    public function testCompileSelectionsDetectsHalt(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $compiled = $svc->compileSelections([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_alarmas' => 'alarma_grupo_pecho_respiracion',
        ]);
        $this->assertTrue($compiled['reserva_triage_halt']);
        $this->assertSame('A', $compiled['urgency_band']);
    }

    public function testFlujoCortoSintomaNuevo(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $compiled = $svc->compileSelections([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_alarmas' => 'alarma_ninguna',
            'triage_detalle' => 'det_cabeza_dolor',
        ]);
        $this->assertFalse($compiled['reserva_triage_halt']);
        $this->assertSame('det_cabeza_dolor', $compiled['reserva_triage_code']);
        $svc->assertCanPersistBooking([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_alarmas' => 'alarma_ninguna',
            'triage_detalle' => 'det_cabeza_dolor',
        ]);
    }

    public function testAssertCanPersistRejectsHalt(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->assertCanPersistBooking([
            'triage_raiz' => 'sintoma_nuevo',
            'triage_alarmas' => 'alarma_grupo_sangrado_neuro',
        ]);
    }
}
