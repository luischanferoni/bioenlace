<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoLifecycleService;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;

class TurnoLifecycleActorInferenceTest extends Unit
{
    public function testInferActorForCancelUsesNewMotivos(): void
    {
        $life = new TurnoLifecycleService();
        $ref = new \ReflectionClass($life);
        $m = $ref->getMethod('inferActorForCancel');
        $m->setAccessible(true);

        $this->assertSame(
            TurnoEventoAudit::ACTOR_SISTEMA,
            $m->invoke($life, Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA, 'sistema', [])
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_EFECTOR,
            $m->invoke($life, Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR, 'admin', [])
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_PACIENTE,
            $m->invoke($life, Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE, 'app', [])
        );
        $this->assertSame(
            TurnoEventoAudit::ACTOR_REPRESENTANTE,
            $m->invoke($life, Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE, 'app', [
                'actor_type' => TurnoEventoAudit::ACTOR_REPRESENTANTE,
            ])
        );
    }

    public function testInferActorForCreate(): void
    {
        $life = new TurnoLifecycleService();
        $ref = new \ReflectionClass($life);
        $m = $ref->getMethod('inferActorForCreate');
        $m->setAccessible(true);

        $this->assertSame(TurnoEventoAudit::ACTOR_PACIENTE, $m->invoke($life, 'app', null));
        $this->assertSame(TurnoEventoAudit::ACTOR_STAFF, $m->invoke($life, 'admin', 12));
        $this->assertSame(TurnoEventoAudit::ACTOR_SISTEMA, $m->invoke($life, 'sistema', null));
    }
}
