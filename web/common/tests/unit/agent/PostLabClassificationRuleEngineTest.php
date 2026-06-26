<?php

namespace common\tests\unit\agent;

use common\components\Domain\Clinical\Laboratory\Service\PostLabClassificationRuleEngine;
use common\tests\unit\DbTestCase;

class PostLabClassificationRuleEngineTest extends DbTestCase
{
    public function testGlucoseElevatedClassifiesControl(): void
    {
        $config = [
            'default_severity' => 'normal',
            'severity_order' => ['normal', 'control', 'critical'],
            'analyte_rules' => [
                [
                    'id' => 'glucose',
                    'loinc' => '2345-7',
                    'severity' => 'control',
                    'when' => ['gte' => 126],
                ],
            ],
        ];

        $result = PostLabClassificationRuleEngine::classify([
            ['loinc' => '2345-7', 'value' => 140, 'unit' => 'mg/dL', 'display' => 'Glucosa'],
        ], $config);

        $this->assertSame('control', $result['severity']);
        $this->assertCount(1, $result['matched_rules']);
    }

    public function testPotassiumCriticalByInterpretation(): void
    {
        $config = [
            'default_severity' => 'normal',
            'severity_order' => ['normal', 'control', 'critical'],
            'analyte_rules' => [
                [
                    'id' => 'potassium',
                    'loinc' => '2823-3',
                    'severity' => 'critical',
                    'when' => ['interpretation_in' => ['HH', 'A']],
                ],
            ],
        ];

        $result = PostLabClassificationRuleEngine::classify([
            ['loinc' => '2823-3', 'value' => 5.2, 'interpretation' => 'HH', 'display' => 'Potasio'],
        ], $config);

        $this->assertSame('critical', $result['severity']);
    }

    public function testDefaultNormalWhenNoRuleMatches(): void
    {
        $config = [
            'default_severity' => 'normal',
            'severity_order' => ['normal', 'control', 'critical'],
            'analyte_rules' => [],
        ];

        $result = PostLabClassificationRuleEngine::classify([
            ['loinc' => '718-7', 'value' => 14.5, 'display' => 'Hb'],
        ], $config);

        $this->assertSame('normal', $result['severity']);
        $this->assertSame([], $result['matched_rules']);
    }
}
