<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\PacienteContextoService;
use common\models\Person\PersonaPacienteContexto;

class PacienteContextoServiceTest extends Unit
{
    public function testMarcarDomicilioVerificadoRespetaProvinciaManual(): void
    {
        $service = new PacienteContextoService();
        $ctx = new PersonaPacienteContexto();
        $ctx->id_persona = 1;
        $ctx->domicilio_estado = PersonaPacienteContexto::DOMICILIO_PENDIENTE;
        $ctx->id_provincia_contexto = 82;
        $ctx->provincia_contexto_manual = true;

        $service->marcarDomicilioVerificado($ctx, 6);

        $this->assertSame(PersonaPacienteContexto::DOMICILIO_VERIFICADO, $ctx->domicilio_estado);
        $this->assertSame(82, $ctx->id_provincia_contexto);
        $this->assertTrue($ctx->provincia_contexto_manual);
    }

    public function testMarcarDomicilioVerificadoAsignaProvinciaMpiSiNoHayManual(): void
    {
        $service = new PacienteContextoService();
        $ctx = new PersonaPacienteContexto();
        $ctx->id_persona = 1;
        $ctx->domicilio_estado = PersonaPacienteContexto::DOMICILIO_PENDIENTE;
        $ctx->provincia_contexto_manual = false;

        $service->marcarDomicilioVerificado($ctx, 6);

        $this->assertSame(6, $ctx->id_provincia_contexto);
        $this->assertFalse($ctx->provincia_contexto_manual);
    }
}
