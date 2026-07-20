<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultaAsyncChatPolicyCatalogService;

class ConsultaAsyncChatPolicyCatalogServiceTest extends Unit
{
    protected function _before(): void
    {
        ConsultaAsyncChatPolicyCatalogService::resetCache();
    }

    public function testStructuredMedicacionOperaciones(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $ops = $svc->structuredMedicacionOperaciones();
        $this->assertContains('renovacion', $ops);
        $this->assertContains('ajuste', $ops);
    }

    public function testSolicitudMessageTypes(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame('solicitud_renovacion', $svc->solicitudMessageType('renovacion'));
        $this->assertSame('solicitud_ajuste', $svc->solicitudMessageType('ajuste'));
    }

    public function testResolucionesIncluyenCierreStaff(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $options = $svc->resolutionOptions();
        $this->assertArrayHasKey('medicacion_renovada', $options);
        $this->assertArrayHasKey('medicacion_ajustada', $options);
        $this->assertArrayHasKey('limite_conversacion', $options);
    }

    public function testMensajeRenovacionConAjustePendiente(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $msg = $svc->duplicateMessage('renovacion_con_ajuste_pendiente');
        $this->assertStringContainsString('ajuste', mb_strtolower($msg));
    }
}
