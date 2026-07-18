<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileContract;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileBackfillService;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;

class TurnoBehaviorProfileBackfillActorTest extends Unit
{
    public function testActorFromCancelMotivoNeverBlamesPatientForSystem(): void
    {
        $svc = new TurnoBehaviorProfileBackfillService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('actorFromCancelMotivo');
        $m->setAccessible(true);

        $this->assertSame(
            TurnoEventoAudit::ACTOR_SISTEMA,
            $m->invoke($svc, Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA)
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_EFECTOR,
            $m->invoke($svc, Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR)
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_PACIENTE,
            $m->invoke($svc, Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE)
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_STAFF,
            $m->invoke($svc, '')
        );
    }

    public function testContractMapsLegacyCreate(): void
    {
        TurnoBehaviorProfileContract::resetCacheForTests();
        $c = new TurnoBehaviorProfileContract();
        $this->assertSame(
            TurnoEventoAudit::EVENT_APPOINTMENT_CREATED,
            $c->eventCodeForLegacyTipo(TurnoEventoAudit::TIPO_CREATE)
        );
        $this->assertContains(90, $c->windowsDays());
        $this->assertNotContains('risk_level', array_column($c->metrics(), 'code'));
    }
}
