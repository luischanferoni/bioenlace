<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\CareCohort\Presentation\CareEducationModuleResolver;
use Codeception\Test\Unit;

class CareEducationModuleResolverTest extends Unit
{
    public function testResolveByRefs(): void
    {
        $resolver = new CareEducationModuleResolver();
        $modules = $resolver->resolveModules(
            [
                'version' => 1,
                'modules' => [
                    ['id' => 'm1', 'title' => 'A'],
                    ['id' => 'm2', 'title' => 'B'],
                ],
            ],
            ['m2']
        );

        $this->assertCount(1, $modules);
        $this->assertSame('m2', $modules[0]['id']);
    }
}
