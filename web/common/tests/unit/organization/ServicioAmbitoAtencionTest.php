<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\models\Organization\Servicio;

final class ServicioAmbitoAtencionTest extends Unit
{
    public function testVeterinariaEsAmbitoVeterinario(): void
    {
        $this->assertSame(Servicio::AMBITO_VETERINARIO, Servicio::ambitoAtencionPorNombre('VETERINARIA'));
        $this->assertSame(Servicio::AMBITO_VETERINARIO, Servicio::ambitoAtencionPorNombre(' VETERINARIA '));
    }

    public function testOtrosNombresSonHumanos(): void
    {
        $this->assertSame(Servicio::AMBITO_HUMANO, Servicio::ambitoAtencionPorNombre('Clínica médica'));
        $this->assertSame(Servicio::AMBITO_HUMANO, Servicio::ambitoAtencionPorNombre('Veterinaria y más'));
        $this->assertSame(Servicio::AMBITO_HUMANO, Servicio::ambitoAtencionPorNombre(null));
    }
}
