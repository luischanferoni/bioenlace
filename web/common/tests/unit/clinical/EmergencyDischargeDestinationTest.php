<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Domain\EmergencyDischargeDestination;

class EmergencyDischargeDestinationTest extends Unit
{
    public function testValuesAndLabels(): void
    {
        $this->assertContains(EmergencyDischargeDestination::ALTA_DOMICILIARIA, EmergencyDischargeDestination::values());
        $this->assertSame('Alta domiciliaria', EmergencyDischargeDestination::label(EmergencyDischargeDestination::ALTA_DOMICILIARIA));
        $this->assertTrue(EmergencyDischargeDestination::requiresPautasAlarma(EmergencyDischargeDestination::ALTA_DOMICILIARIA));
        $this->assertFalse(EmergencyDischargeDestination::requiresPautasAlarma(EmergencyDischargeDestination::FUGA));
        $this->assertTrue(EmergencyDischargeDestination::requiresEfectorDerivacion(EmergencyDischargeDestination::DERIVACION));
        $this->assertTrue(EmergencyDischargeDestination::requestsInternacion(EmergencyDischargeDestination::INTERNACION));
        $this->assertNotEmpty(EmergencyDischargeDestination::options());
        $this->assertSame('Paciente se retiró / fuga / abandono', EmergencyDischargeDestination::label(EmergencyDischargeDestination::FUGA));
        $this->assertTrue(EmergencyDischargeDestination::isAdministrativo(EmergencyDischargeDestination::FUGA));
        $this->assertFalse(EmergencyDischargeDestination::isAdministrativo(EmergencyDischargeDestination::ALTA_DOMICILIARIA));
        $adminOpts = EmergencyDischargeDestination::optionsForModo(EmergencyDischargeDestination::MODO_ADMINISTRATIVO);
        $this->assertCount(1, $adminOpts);
        $this->assertSame(EmergencyDischargeDestination::FUGA, $adminOpts[0]['value']);
        $clinOpts = EmergencyDischargeDestination::optionsForModo(EmergencyDischargeDestination::MODO_CLINICO);
        $this->assertGreaterThan(1, count($clinOpts));
    }
}
