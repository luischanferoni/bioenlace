<?php

namespace common\tests\unit\person;

use common\components\Domain\Person\Service\PersonaAsistentePreferenciasService;

class PersonaAsistentePreferenciasServiceTest extends \Codeception\Test\Unit
{
    public function testDefaultEncendidoSinFila()
    {
        $svc = new PersonaAsistentePreferenciasService();

        verify($svc->defaultsArray()['usa_resumen_hc_en_asistente'])->true();
        verify($svc->getForPersona(0)['usa_resumen_hc_en_asistente'])->true();
    }

    public function testSaveRequiereFlag()
    {
        $svc = new PersonaAsistentePreferenciasService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->saveForPersona(1, []);
    }
}
