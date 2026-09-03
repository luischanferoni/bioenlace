<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Assistant\Copy\IntentMatchLaunchCopy;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

class IntentMatchLaunchCopyTest extends Unit
{
    protected function _after(): void
    {
        AssistantChannelCopy::resetCacheForTests();
    }

    public function testLeadUsesLabelNotNecesidadAndDropsShortStepTitle(): void
    {
        $item = $this->item('Turno con un especialista', 'Reservá un turno eligiendo servicio.');
        $text = IntentMatchLaunchCopy::forFlowLaunch($item, 'Servicio', []);

        $this->assertStringContainsString('Turno con un especialista', $text);
        $this->assertStringNotContainsString('Informar un efecto', $text);
        $this->assertStringNotContainsString('Iniciar:', $text);
        $this->assertStringNotContainsString('Servicio', $text);
    }

    public function testSpansEnrichLabelWithoutDumpingInternalNecesidad(): void
    {
        $item = $this->item('Solicitar Atención');
        $text = IntentMatchLaunchCopy::forFlowLaunch($item, '', ['enalapril']);

        $this->assertStringContainsString('Solicitar Atención', $text);
        $this->assertStringContainsString('enalapril', $text);
    }

    public function testAppendsUserFacingStepQuestion(): void
    {
        $item = $this->item('Solicitar Atención');
        $text = IntentMatchLaunchCopy::forFlowLaunch($item, '¿Qué te está pasando?', []);

        $this->assertStringContainsString('Solicitar Atención', $text);
        $this->assertStringContainsString('¿Qué te está pasando?', $text);
    }

    public function testFallbackUsesLabel(): void
    {
        $item = $this->item('Ver laboratorio');
        $text = IntentMatchLaunchCopy::forFlowLaunch($item, '', []);

        $this->assertStringContainsString('Ver laboratorio', $text);
        $this->assertStringNotContainsString('Abrir:', $text);
    }

    public function testOpenActionUsesLabel(): void
    {
        $item = $this->item('Mis recetas');
        $text = IntentMatchLaunchCopy::forOpenAction($item, []);

        $this->assertStringContainsString('Mis recetas', $text);
        $this->assertStringNotContainsString('Ver mis recetas vigentes', $text);
    }

    public function testUniqueSpansDedupesCase(): void
    {
        $spans = IntentMatchLaunchCopy::uniqueSpans([
            ['span' => 'cardiologo'],
            ['span' => 'Cardiologo'],
            ['span' => ''],
            ['span' => 'turno'],
        ]);

        $this->assertSame(['cardiologo', 'turno'], $spans);
    }

    private function item(string $name, string $summary = ''): UiActionCatalogItem
    {
        $sem = $summary !== '' ? ['summary' => $summary] : null;

        return new UiActionCatalogItem(
            'test.intent',
            $name,
            '',
            null,
            '/api/test',
            [],
            [],
            $sem
        );
    }
}
