<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Agenda\Application\Service\ReservaModalidadAtencionCatalogService;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\ResolveReservaModalidadAtencion;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\EvaluateTeleconsultaEligibility;

class ResolveReservaModalidadAtencionTest extends Unit
{
    protected function _before(): void
    {
        ReservaModalidadAtencionCatalogService::resetCache();
    }

    public function testOpcionesIncluyenPresencialYAsyncParaSeguimiento(): void
    {
        $svc = new ResolveReservaModalidadAtencion();
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
        $svc = new ResolveReservaModalidadAtencion();
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
        (new ResolveReservaModalidadAtencion())->aplicarFlagsEnDraft($draft);

        $this->assertSame('1', $draft['modalidad_paso_requerido'] ?? null);
        $this->assertSame('1', $draft['async_ofrecible'] ?? null);
    }

    public function testCatalogoDeclaraAsyncElegibilidadesYRaiz(): void
    {
        $catalog = new ReservaModalidadAtencionCatalogService();
        $eleg = $catalog->elegibilidadesParaAsync();
        $this->assertContains(EvaluateTeleconsultaEligibility::ELEG_SUGERIDO, $eleg);
        $this->assertContains(EvaluateTeleconsultaEligibility::ELEG_PERMITIDO, $eleg);
        $this->assertContains('seguimiento_cronico', $catalog->triageRaicesParaAsync());
    }

    public function testEstudioPedidoSoloPresencialCuandoTeleconsultaExcluida(): void
    {
        $this->seedTeleconsultaElegibilidad('estudio_pedido', EvaluateTeleconsultaEligibility::ELEG_EXCLUIDO);

        $svc = new ResolveReservaModalidadAtencion();
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
        if (!class_exists(\common\models\Scheduling\ReservaTriageTeleconsultaElegibilidad::class)) {
            $this->markTestSkipped('ReservaTriageTeleconsultaElegibilidad no disponible');
        }
        \common\models\Scheduling\ReservaTriageTeleconsultaElegibilidad::resetCache();
        $row = \common\models\Scheduling\ReservaTriageTeleconsultaElegibilidad::findOne(['triage_codigo' => $codigo]);
        if ($row === null) {
            $row = new \common\models\Scheduling\ReservaTriageTeleconsultaElegibilidad();
            $row->triage_codigo = $codigo;
        }
        $row->elegibilidad = $elegibilidad;
        $row->prioridad = 40;
        if (!$row->save(false)) {
            $this->markTestSkipped('No se pudo sembrar elegibilidad de teleconsulta en BD de test');
        }
        \common\models\Scheduling\ReservaTriageTeleconsultaElegibilidad::resetCache();
    }
}
