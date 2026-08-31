<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusResolver;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideFocusState;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;

class GuideFocusResolverTest extends Unit
{
    public function testResolvesPrimaryFromPreprocessAreas(): void
    {
        $state = GuideFocusResolver::resolve(
            [AssistantContextHISArea::CLINICAL_RECORD, AssistantContextHISArea::APPOINTMENTS],
            null,
            true
        );
        $this->assertSame(AssistantContextHISArea::APPOINTMENTS, $state->primaryArea);
        $this->assertSame('guide:appointments', $state->threadTag());
    }

    public function testCarriesPreviousFocusOnGreeting(): void
    {
        $prev = [
            'primary_area' => AssistantContextHISArea::APPOINTMENTS,
            'active_areas' => [AssistantContextHISArea::APPOINTMENTS],
        ];
        $state = GuideFocusResolver::resolve([], $prev, true);
        $this->assertSame(AssistantContextHISArea::APPOINTMENTS, $state->primaryArea);
    }

    public function testNoCarryWhenDisabled(): void
    {
        $prev = [
            'primary_area' => AssistantContextHISArea::APPOINTMENTS,
            'active_areas' => [AssistantContextHISArea::APPOINTMENTS],
        ];
        $state = GuideFocusResolver::resolve([], $prev, false);
        $this->assertTrue($state->isEmpty());
    }

    public function testMetadataRoundtrip(): void
    {
        $state = new GuideFocusState(
            AssistantContextHISArea::APPOINTMENTS,
            [AssistantContextHISArea::APPOINTMENTS]
        );
        $restored = GuideFocusState::fromMetadataArray($state->toMetadataArray());
        $this->assertNotNull($restored);
        $this->assertSame($state->primaryArea, $restored->primaryArea);
        $this->assertSame($state->activeAreas, $restored->activeAreas);
    }
}
