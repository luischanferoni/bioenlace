<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Domain\TriageScale;

class TriageScaleTest extends Unit
{
    public function testLevelMetadataProvidesOrderedLabelsColorsAndIntents(): void
    {
        $levels = TriageScale::levelMetadata();

        $this->assertSame([1, 2, 3, 4, 5], array_column($levels, 'level'));
        $this->assertSame(
            ['danger', 'danger', 'warning', 'success', 'info'],
            array_column($levels, 'intent')
        );
        $this->assertSame('#c0392b', $levels[0]['color']);
        $this->assertSame('Azul — no urgente', $levels[4]['label']);
    }
}
