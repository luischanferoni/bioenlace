<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ReservaModalidadAtencionCatalogService;
use common\components\Domain\Scheduling\Service\ReservaModalidadAtencionService;
use common\components\Domain\Scheduling\Service\TeleconsultaElegibilidadService;

class ReservaModalidadAtencionServiceTest extends Unit
{
    protected function _before(): void
    {
        ReservaModalidadAtencionCatalogService::resetCache();
    }

    public function testOpcionesIncluyenPresencialYAsyncParaSeguimiento(): void
    {
        $svc = new ReservaModalidadAtencionService();
        $opts = $svc->opcionesParaDraft([
            'triage_raiz' => 'seguimiento_cronico',
            'triage_evolucion' => 'evolucion_estable',
        ]);

        $codes = array_column($opts, 'code');
        $this->assertContains('presencial', $codes);
        $this->assertContains('async', $codes);
    }

    public function testAsyncNoSeOfreceFueraDeControlSeguimiento(): void
    {
        $svc = new ReservaModalidadAtencionService();
        $opts = $svc->opcionesParaDraft([
            'triage_raiz' => 'malestar_nuevo',
            'triage_zona' => 'zona_sistemas',
        ]);

        $codes = array_column($opts, 'code');
        $this->assertContains('presencial', $codes);
        $this->assertNotContains('async', $codes);
    }

    public function testAplicarFlagsRequierePasoConVariasModalidades(): void
    {
        $draft = [
            'triage_raiz' => 'seguimiento_cronico',
            'triage_evolucion' => 'evolucion_estable',
        ];
        (new ReservaModalidadAtencionService())->aplicarFlagsEnDraft($draft);

        $this->assertSame('1', $draft['modalidad_paso_requerido'] ?? null);
        $this->assertSame('1', $draft['async_ofrecible'] ?? null);
    }

    public function testCatalogoDeclaraAsyncElegibilidadesYRaiz(): void
    {
        $catalog = new ReservaModalidadAtencionCatalogService();
        $eleg = $catalog->elegibilidadesParaAsync();
        $this->assertContains(TeleconsultaElegibilidadService::ELEG_SUGERIDO, $eleg);
        $this->assertContains(TeleconsultaElegibilidadService::ELEG_PERMITIDO, $eleg);
        $this->assertContains('seguimiento_cronico', $catalog->triageRaicesParaAsync());
    }

    public function testEstudioPedidoSoloPresencialCuandoTeleconsultaExcluida(): void
    {
        $this->seedTeleconsultaElegibilidad('estudio_pedido', TeleconsultaElegibilidadService::ELEG_EXCLUIDO);

        $svc = new ReservaModalidadAtencionService();
        $opts = $svc->opcionesParaDraft([
            'triage_raiz' => 'estudio_pedido',
            'pedido_acto' => 'http://snomed.info/sct|16310003',
        ]);

        $codes = array_column($opts, 'code');
        $this->assertSame(['presencial'], $codes);

        $draft = [
            'triage_raiz' => 'estudio_pedido',
            'pedido_acto' => 'http://snomed.info/sct|16310003',
        ];
        $svc->aplicarFlagsEnDraft($draft);
        $this->assertSame('0', $draft['teleconsulta_ofrecible'] ?? null);
        $this->assertSame('0', $draft['modalidad_paso_requerido'] ?? null);
        $this->assertSame('presencial', $draft['tipo_atencion'] ?? null);
    }

    private function seedTeleconsultaElegibilidad(string $codigo, string $elegibilidad): void
    {
        if (!class_exists(\common\models\ReservaTriageTeleconsultaElegibilidad::class)) {
            $this->markTestSkipped('ReservaTriageTeleconsultaElegibilidad no disponible');
        }
        \common\models\ReservaTriageTeleconsultaElegibilidad::resetCache();
        $row = \common\models\ReservaTriageTeleconsultaElegibilidad::findOne(['triage_codigo' => $codigo]);
        if ($row === null) {
            $row = new \common\models\ReservaTriageTeleconsultaElegibilidad();
            $row->triage_codigo = $codigo;
        }
        $row->elegibilidad = $elegibilidad;
        $row->prioridad = 40;
        if (!$row->save(false)) {
            $this->markTestSkipped('No se pudo sembrar elegibilidad de teleconsulta en BD de test');
        }
        \common\models\ReservaTriageTeleconsultaElegibilidad::resetCache();
    }
}
