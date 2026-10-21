<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterAsyncInboxCatalogService;

class EncounterAsyncInboxCatalogServiceTest extends Unit
{
    protected function _before(): void
    {
        EncounterAsyncInboxCatalogService::resetCache();
    }

    public function testSlaHorasPorBandaUrgencia(): void
    {
        $svc = new EncounterAsyncInboxCatalogService();
        $this->assertSame(4, $svc->horasSlaRespuesta('A'));
        $this->assertSame(24, $svc->horasSlaRespuesta('C'));
        $this->assertSame(48, $svc->horasSlaRespuesta(null));
    }

    public function testTitulosSeccionUsanConsultaClinicaPorMensaje(): void
    {
        $svc = new EncounterAsyncInboxCatalogService();
        $this->assertSame('Consultas clínicas por mensaje', $svc->tituloSeccionStaff());
        $this->assertSame('Consultas clínicas por mensaje', $svc->tituloSeccionPaciente());
    }

    public function testMensajesSolicitudRenovacionEnCatalogo(): void
    {
        $svc = new EncounterAsyncInboxCatalogService();
        $this->assertStringContainsString('renovación', $svc->mensajeExitoRenovacion());
        $this->assertStringContainsString('Consultas clínicas por mensaje', $svc->mensajeExitoRenovacionCierre());
        $this->assertStringContainsString('pendiente', $svc->mensajeRenovacionDuplicada());
    }

    public function testStaffGroupsPorTomarYMias(): void
    {
        $svc = new EncounterAsyncInboxCatalogService();
        $groups = $svc->staffGroups();
        $ids = array_column($groups, 'id');
        $this->assertSame(['mias', 'por_tomar'], $ids);
        $this->assertSame('Las mías', $groups[0]['title']);
        $this->assertSame('Por tomar', $groups[1]['title']);
    }
}
