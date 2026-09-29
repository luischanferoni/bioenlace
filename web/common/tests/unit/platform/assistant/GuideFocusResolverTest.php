<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusResolver;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;

class GuideFocusResolverTest extends Unit
{
    public function testIgnoresPassedAreasWithoutCarry(): void
    {
        $state = GuideFocusResolver::resolve(
            ['clinical', 'scheduling'],
            null,
            true
        );
        $this->assertTrue($state->isEmpty());
        $this->assertSame('guide', $state->threadTag());
    }

    public function testCarriesPreviousFocusOnGreeting(): void
    {
        $prev = [
            'primary_area' => 'scheduling',
            'active_areas' => ['scheduling'],
        ];
        $state = GuideFocusResolver::resolve([], $prev, true);
        $this->assertSame('scheduling', $state->primaryArea);
    }

    public function testNoCarryWhenDisabled(): void
    {
        $prev = [
            'primary_area' => 'scheduling',
            'active_areas' => ['scheduling'],
        ];
        $state = GuideFocusResolver::resolve([], $prev, false);
        $this->assertTrue($state->isEmpty());
    }

    public function testMetadataRoundtrip(): void
    {
        $state = new GuideFocusState('scheduling', ['scheduling']);
        $restored = GuideFocusState::fromMetadataArray($state->toMetadataArray());
        $this->assertNotNull($restored);
        $this->assertSame($state->primaryArea, $restored->primaryArea);
        $this->assertSame($state->activeAreas, $restored->activeAreas);
    }
}
