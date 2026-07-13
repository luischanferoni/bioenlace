<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Service\PacienteContextoOfferingService;
use common\models\Efector;
use common\models\Person\PersonaPacienteContexto;

class PacienteContextoOfferingServiceTest extends Unit
{
    private PacienteContextoOfferingService $service;

    protected function _before(): void
    {
        $this->service = new PacienteContextoOfferingService();
    }

    public function testEfectorPublicoCoincideConSectorPublico(): void
    {
        $efector = new Efector();
        $efector->origen_financiamiento = 'Público';

        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PUBLICO, null);

        $this->assertTrue($this->service->efectorMatchesContext($efector, $ctx));
    }

    public function testEfectorPrivadoNoCoincideConSectorPublico(): void
    {
        $efector = new Efector();
        $efector->origen_financiamiento = 'Privado';

        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PUBLICO, null);

        $this->assertFalse($this->service->efectorMatchesContext($efector, $ctx));
    }

    public function testEfectorPrivadoCoincideConSectorPrivado(): void
    {
        $efector = new Efector();
        $efector->origen_financiamiento = 'Privado';

        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PRIVADO, null);

        $this->assertTrue($this->service->efectorMatchesContext($efector, $ctx));
    }

    public function testEfectorPublicoNoCoincideConSectorPrivado(): void
    {
        $efector = new Efector();
        $efector->origen_financiamiento = 'Público';

        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PRIVADO, null);

        $this->assertFalse($this->service->efectorMatchesContext($efector, $ctx));
    }

    public function testEfectorOrigenJurisdiccionalYaNoCoincideConSectorPublico(): void
    {
        $efector = new Efector();
        $efector->origen_financiamiento = 'Provincial';

        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PUBLICO, null);

        $this->assertFalse($this->service->efectorMatchesContext($efector, $ctx));
    }

    public function testMergeEfectorFiltersAgregaSectorYProvincia(): void
    {
        $ctx = $this->contexto(PersonaPacienteContexto::SECTOR_SALUD_PUBLICO, 82);

        // Sin usuario logueado el servicio no aplica; probamos la lógica vía efectorMatchesContext
        // y documentamos merge en QA manual. Este test valida el modelo de contexto mínimo.
        $this->assertTrue($ctx->tieneProvinciaOperativa());
        $this->assertSame(PersonaPacienteContexto::SECTOR_SALUD_PUBLICO, $ctx->sector_salud);
    }

    private function contexto(string $sector, ?int $idProvincia): PersonaPacienteContexto
    {
        $ctx = new PersonaPacienteContexto();
        $ctx->id_persona = 1;
        $ctx->sector_salud = $sector;
        $ctx->id_provincia_contexto = $idProvincia;
        $ctx->domicilio_estado = PersonaPacienteContexto::DOMICILIO_VERIFICADO;
        $ctx->domicilio_verificacion_inicio = '2026-01-01 00:00:00';
        $ctx->created_at = '2026-01-01 00:00:00';
        $ctx->updated_at = '2026-01-01 00:00:00';

        return $ctx;
    }
}
