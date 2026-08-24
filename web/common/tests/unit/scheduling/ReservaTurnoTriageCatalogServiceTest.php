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

    public function testRaizListaControlSeguimiento(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $options = $svc->getOptionsForStep('raiz');
        $codes = array_column($options, 'code');
        $this->assertContains('malestar_nuevo', $codes);
        $this->assertContains('seguimiento_cronico', $codes);
        $this->assertContains('urgencia', $codes);
        $byCode = [];
        foreach ($options as $row) {
            $byCode[(string) ($row['code'] ?? '')] = $row;
        }
        $this->assertSame('Control/Seguimiento', $byCode['seguimiento_cronico']['label'] ?? null);
        $this->assertSame('Estudio o práctica', $byCode['estudio_pedido']['label'] ?? null);
    }

    public function testZonaListaSistemasCorporales(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $options = $svc->getOptionsForStep('zona', 'malestar_nuevo');
        $codes = array_column($options, 'code');
        $this->assertContains('zona_genitourinario', $codes);
        $this->assertContains('zona_general', $codes);
        $this->assertContains('zona_musculoesqueletico', $codes);
        $this->assertNotContains('zona_espalda', $codes);
        $this->assertCount(8, $codes);
    }

    public function testInfiereZonaGeneralDesdeAnsiedadEInsomnio(): void
    {
        $svc = new ReservaTurnoTriageCatalogService();
        $this->assertSame(
            'zona_general',
            $svc->inferZonaCodeFromText('Estoy muy ansioso y no puedo dormir')
        );
        $this->assertSame(
            'zona_abdomen',
            $svc->inferZonaCodeFromText('Me duele la panza del lado derecho')
        );
        $this->assertSame(
            'zona_cabeza_cuello',
            $svc->inferZonaCodeFromText('Me duele la cabeza y estoy ansioso')
        );
        $this->assertNull($svc->inferZonaCodeFromText('Quiero un turno'));
    }
}
