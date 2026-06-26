<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;

class TurnoResolucionLoopCloseRulesTest extends Unit
{
    protected function _before(): void
    {
        AutonomousAgentMetadata::resetCacheForTests();
    }

    public function testChronicBandEscalatesStaff(): void
    {
        $rules = AutonomousAgentMetadata::rulesForAgent('turno-resolucion-loop-close');
        $matched = AutonomousAgentRuleEngine::matchAll($rules, [
            'urgency_band' => 'C',
            'resolucion_origen' => 'licencia',
            'horas_sin_respuesta' => 80,
        ], null);

        $this->assertNotEmpty($matched);
        $this->assertSame('escalate_staff', $matched[0]['action']);
        $this->assertSame('chronic_escalate_coordination', $matched[0]['id']);
    }

    public function testDefaultBandUsesNoRuleMatch(): void
    {
        $rules = AutonomousAgentMetadata::rulesForAgent('turno-resolucion-loop-close');
        $matched = AutonomousAgentRuleEngine::matchAll($rules, [
            'urgency_band' => 'B',
            'resolucion_origen' => 'cambio_agenda',
            'horas_sin_respuesta' => 80,
        ], null);

        $this->assertSame([], $matched);
        $config = AutonomousAgentMetadata::loadAgent('turno-resolucion-loop-close');
        $this->assertSame('cancel_turno', $config['default_action'] ?? null);
    }
}
