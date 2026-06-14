<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\CareCohort\CohortKeyBuilder;
use Codeception\Test\Unit;

class CohortKeyBuilderTest extends Unit
{
    public function testHashProfileIsStable(): void
    {
        $builder = new CohortKeyBuilder();
        $profile = [
            'life_stage' => '40-64',
            'sexo' => 'F',
            'conditions' => ['hta', 'dm2'],
            'motive_cluster' => 'general',
            'jurisdiction' => 'prov-22',
            'season' => 'Q2',
        ];

        $a = $builder->hashProfile($profile);
        $b = $builder->hashProfile(array_reverse($profile));

        $this->assertSame(64, strlen($a));
        $this->assertSame($a, $b);
    }

    public function testConditionsOrderDoesNotChangeHash(): void
    {
        $builder = new CohortKeyBuilder();
        $p1 = [
            'life_stage' => '18-39',
            'sexo' => 'M',
            'conditions' => ['asma', 'hta'],
            'motive_cluster' => 'general',
            'jurisdiction' => 'unknown',
            'season' => 'Q1',
        ];
        $p2 = $p1;
        $p2['conditions'] = ['hta', 'asma'];

        $this->assertSame($builder->hashProfile($p1), $builder->hashProfile($p2));
    }
}
