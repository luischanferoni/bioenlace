<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Context\AssistantContextAreaDerivation;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;

class AssistantContextAreaDerivationTest extends Unit
{
    public function testDerivesDomainFromIntentIds(): void
    {
        $areas = AssistantContextAreaDerivation::fromIntentIds([
            'turnos.crear-como-paciente',
        ]);

        $this->assertSame([AssistantContextHISArea::SCHEDULING], $areas);
    }

    public function testDerivesMultipleDomainsFromIntentIds(): void
    {
        $areas = AssistantContextAreaDerivation::fromIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ]);

        $this->assertContains(AssistantContextHISArea::SCHEDULING, $areas);
        $this->assertContains(AssistantContextHISArea::CLINICAL, $areas);
    }

    public function testEmptyIntentIds(): void
    {
        $this->assertSame([], AssistantContextAreaDerivation::fromIntentIds([]));
        $this->assertSame([], AssistantContextAreaDerivation::fromIntentIds(['', '  ']));
    }
}
