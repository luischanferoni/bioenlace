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

    public function testSolicitudCategoriasCanonicaYLabels(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_RENOVACION_MEDICACION,
            $svc->resolveSolicitudCategoria('renovacion')
        );
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_AJUSTE_MEDICACION,
            $svc->resolveSolicitudCategoria('solicitar_ajuste')
        );
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_CONSULTA_EVOLUCION,
            $svc->resolveSolicitudCategoria('consulta_general')
        );
        $this->assertSame(
            'Solicitud de renovación de medicación',
            $svc->solicitudCategoriaLabel('renovacion')
        );
        $this->assertSame(
            'Solicitud de ajuste de medicación',
            $svc->solicitudTipoLabel('ajuste')
        );
        $this->assertSame(
            'Consulta o evolución',
            $svc->solicitudTipoLabel('contar_evolucion')
        );
    }

    public function testSolicitudCategoriaFromMeta(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_RENOVACION_MEDICACION,
            $svc->solicitudCategoriaFromMeta(['medicacion_operacion' => 'renovacion'])
        );
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_CONSULTA_EVOLUCION,
            $svc->solicitudCategoriaFromMeta(['intake_tipo' => 'consulta_general'])
        );
    }

    public function testSolicitudCategoriaFromLegacyMessageType(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame(
            ConsultaAsyncChatPolicyCatalogService::CATEGORIA_AJUSTE_MEDICACION,
            $svc->solicitudCategoriaFromLegacyMessageType('solicitud_ajuste')
        );
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

    public function testAllowedUploadTypesStaffAudioYDocumento(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame(['audio', 'documento'], $svc->allowedUploadMessageTypes());
    }

    public function testAllowedUploadTypesPacienteSoloImagen(): void
    {
        $svc = new ConsultaAsyncChatPolicyCatalogService();
        $this->assertSame(['imagen'], $svc->allowedUploadMessageTypesForPatient());
    }
}
