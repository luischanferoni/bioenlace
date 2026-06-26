<?php

namespace common\tests\unit\agent;

use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\tests\unit\DbTestCase;

class AutonomousAgentRuleEngineTest extends DbTestCase
{
    public function testMatchWorseningEvolution(): void
    {
        $rules = [
            [
                'id' => 'worsening',
                'form_kinds' => ['evolution_short'],
                'when' => ['field' => 'comparacion', 'equals' => 'peor'],
                'action' => 'notify_staff',
            ],
        ];

        $matched = AutonomousAgentRuleEngine::matchAll(
            $rules,
            ['comparacion' => 'peor'],
            'evolution_short'
        );

        $this->assertCount(1, $matched);
        $this->assertSame('worsening', $matched[0]['id']);
    }

    public function testSkipsRuleForWrongFormKind(): void
    {
        $rules = [
            [
                'id' => 'worsening',
                'form_kinds' => ['evolution_short'],
                'when' => ['field' => 'comparacion', 'equals' => 'peor'],
                'action' => 'notify_staff',
            ],
        ];

        $matched = AutonomousAgentRuleEngine::matchAll(
            $rules,
            ['comparacion' => 'peor'],
            'adherence'
        );

        $this->assertSame([], $matched);
    }

    public function testIntensidadGte(): void
    {
        $rules = [
            [
                'id' => 'high_intensity',
                'form_kinds' => ['symptoms'],
                'when' => ['field' => 'intensidad', 'gte' => 8],
                'action' => 'notify_staff',
            ],
        ];

        $matched = AutonomousAgentRuleEngine::matchAll(
            $rules,
            ['intensidad' => '9'],
            'symptoms'
        );

        $this->assertCount(1, $matched);
    }
}
