<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusResolver;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;

class GuideFocusResolverTest extends Unit
{
    public function testResolvesPrimaryFromPreprocessAreas(): void
    {
        $state = GuideFocusResolver::resolve(
            [AssistantContextHISArea::CLINICAL, AssistantContextHISArea::SCHEDULING],
            null,
            true
        );
        $this->assertSame(AssistantContextHISArea::SCHEDULING, $state->primaryArea);
        $this->assertSame('guide:scheduling', $state->threadTag());
    }

    public function testCarriesPreviousFocusOnGreeting(): void
    {
        $prev = [
            'primary_area' => AssistantContextHISArea::SCHEDULING,
            'active_areas' => [AssistantContextHISArea::SCHEDULING],
        ];
        $state = GuideFocusResolver::resolve([], $prev, true);
        $this->assertSame(AssistantContextHISArea::SCHEDULING, $state->primaryArea);
    }

    public function testNoCarryWhenDisabled(): void
    {
        $prev = [
            'primary_area' => AssistantContextHISArea::SCHEDULING,
            'active_areas' => [AssistantContextHISArea::SCHEDULING],
        ];
        $state = GuideFocusResolver::resolve([], $prev, false);
        $this->assertTrue($state->isEmpty());
    }

    public function testMetadataRoundtrip(): void
    {
        $state = new GuideFocusState(
            AssistantContextHISArea::SCHEDULING,
            [AssistantContextHISArea::SCHEDULING]
        );
        $restored = GuideFocusState::fromMetadataArray($state->toMetadataArray());
        $this->assertNotNull($restored);
        $this->assertSame($state->primaryArea, $restored->primaryArea);
        $this->assertSame($state->activeAreas, $restored->activeAreas);
    }
}
