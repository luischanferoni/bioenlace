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

    public function testCatalogoDeclaraAsyncElegibilidades(): void
    {
        $eleg = (new ReservaModalidadAtencionCatalogService())->elegibilidadesParaAsync();
        $this->assertContains(TeleconsultaElegibilidadService::ELEG_SUGERIDO, $eleg);
        $this->assertContains(TeleconsultaElegibilidadService::ELEG_PERMITIDO, $eleg);
    }
}
