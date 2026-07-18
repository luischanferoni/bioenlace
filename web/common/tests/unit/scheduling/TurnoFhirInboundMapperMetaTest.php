<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Scheduling\Dto\FhirAppointmentInboundDto;
use common\components\Domain\Integrations\Scheduling\Mapper\FhirAppointmentInboundMapper;

class TurnoFhirInboundMapperMetaTest extends Unit
{
    public function testMapsVersionAndLastUpdated(): void
    {
        $mapper = new FhirAppointmentInboundMapper();
        $dto = $mapper->map([
            'resourceType' => 'Appointment',
            'id' => 'ext-1',
            'status' => 'booked',
            'start' => '2026-07-20T10:00:00Z',
            'meta' => [
                'versionId' => '7',
                'lastUpdated' => '2026-07-18T12:00:00Z',
            ],
            'participant' => [],
        ], 'sis');

        $this->assertInstanceOf(FhirAppointmentInboundDto::class, $dto);
        $this->assertSame('ext-1', $dto->externalId);
        $this->assertSame('7', $dto->versionId);
        $this->assertSame('2026-07-18T12:00:00Z', $dto->lastUpdated);
        $this->assertSame('booked', $dto->fhirStatus);
    }
}
