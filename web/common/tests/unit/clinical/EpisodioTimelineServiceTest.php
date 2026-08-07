<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Enum\CircuitoEventType;
use common\components\Domain\Clinical\Service\EpisodioTimelineService;
use common\models\Clinical\Encounter;

/**
 * Timeline de episodio: labels de circuito + helper de parent_type.
 */
class EpisodioTimelineServiceTest extends Unit
{
    public function testCircuitoEventTypeLabels(): void
    {
        $this->assertSame('Ingreso a guardia', CircuitoEventType::label(CircuitoEventType::INGRESO));
        $this->assertSame('Asignación de médico', CircuitoEventType::label(CircuitoEventType::ASIGNACION));
        $this->assertSame('Evento de circuito', CircuitoEventType::label(null));
        $this->assertSame('custom', CircuitoEventType::label('custom'));
    }

    public function testListEncountersForParentAcceptsEmpty(): void
    {
        $svc = new EpisodioTimelineService();
        // Sin filas en BD de test: no debe lanzar; parent inexistente → [].
        $rows = $svc->listEncountersForParent(Encounter::PARENT_GUARDIA, -1, -1);
        $this->assertIsArray($rows);
        $this->assertSame([], $rows);
    }
}
