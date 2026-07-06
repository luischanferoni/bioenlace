<?php

namespace common\tests\unit\integrations\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentStatusMapper;
use common\models\Scheduling\Turno;

class FhirAppointmentStatusMapperTest extends Unit
{
    public function testBookedMapsToPendiente(): void
    {
        $mapped = FhirAppointmentStatusMapper::mapToTurnoEstado('booked');
        $this->assertSame(Turno::ESTADO_PENDIENTE, $mapped['estado']);
        $this->assertSame('booked', $mapped['fhir_status']);
    }

    public function testCancelledMapsToCancelado(): void
    {
        $mapped = FhirAppointmentStatusMapper::mapToTurnoEstado('cancelled');
        $this->assertSame(Turno::ESTADO_CANCELADO, $mapped['estado']);
    }

    public function testFulfilledMapsToAtendido(): void
    {
        $mapped = FhirAppointmentStatusMapper::mapToTurnoEstado('fulfilled');
        $this->assertSame(Turno::ESTADO_ATENDIDO, $mapped['estado']);
    }
}
