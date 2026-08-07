<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Enum\GuardiaEgresoDestino;

class GuardiaEgresoDestinoTest extends Unit
{
    public function testValuesAndLabels(): void
    {
        $this->assertContains(GuardiaEgresoDestino::ALTA_DOMICILIARIA, GuardiaEgresoDestino::values());
        $this->assertSame('Alta domiciliaria', GuardiaEgresoDestino::label(GuardiaEgresoDestino::ALTA_DOMICILIARIA));
        $this->assertTrue(GuardiaEgresoDestino::requiresPautasAlarma(GuardiaEgresoDestino::ALTA_DOMICILIARIA));
        $this->assertFalse(GuardiaEgresoDestino::requiresPautasAlarma(GuardiaEgresoDestino::FUGA));
        $this->assertTrue(GuardiaEgresoDestino::requiresEfectorDerivacion(GuardiaEgresoDestino::DERIVACION));
        $this->assertTrue(GuardiaEgresoDestino::requestsInternacion(GuardiaEgresoDestino::INTERNACION));
        $this->assertNotEmpty(GuardiaEgresoDestino::options());
    }
}
