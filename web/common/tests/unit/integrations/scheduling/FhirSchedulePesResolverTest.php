<?php

namespace common\tests\unit\integrations\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Scheduling\FhirSchedulePesResolver;
use common\components\Domain\Integrations\Scheduling\ScheduleActorSet;

class FhirSchedulePesResolverTest extends Unit
{
    public function testUnresolvedWithoutPractitionerId(): void
    {
        $resolver = new FhirSchedulePesResolver();
        $result = $resolver->resolve('hapi-test', 'sched-1', new ScheduleActorSet());

        $this->assertSame(FhirSchedulePesResolver::TRUST_UNRESOLVED, $result['trust']);
        $this->assertNull($result['id_profesional_efector_servicio']);
    }

    public function testDniOnlyIsUnresolved(): void
    {
        $resolver = new FhirSchedulePesResolver();
        $result = $resolver->resolve('hapi-test', 'sched-2', new ScheduleActorSet(
            practitionerDni: '12345678',
            locationSisa: '00001',
            serviceCodeSystem: 'http://snomed.info/sct',
            serviceCodeValue: 'x'
        ));

        $this->assertSame(FhirSchedulePesResolver::TRUST_UNRESOLVED, $result['trust']);
        $this->assertStringContainsString('CUIL', (string) $result['reason']);
    }

    public function testFingerprintStable(): void
    {
        $resolver = new FhirSchedulePesResolver();
        $actors = new ScheduleActorSet(
            practitionerCuil: '20399998639',
            locationSisa: '12345',
            serviceCodeSystem: 'http://snomed.info/sct',
            serviceCodeValue: '394814009'
        );
        $a = $resolver->fingerprint($actors);
        $b = $resolver->fingerprint($actors);
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
    }
}
