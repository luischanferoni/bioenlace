<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Copy\AssistantChannelCopy;
use common\components\Platform\Core\Product\ClientContextMetadata;

class AssistantChannelCopyTest extends Unit
{
    protected function _after(): void
    {
        AssistantChannelCopy::resetCacheForTests();
        ClientContextMetadata::resetCacheForTests();
    }

    public function testDefaultNoIntentMatchAvoidsPantalla(): void
    {
        $text = AssistantChannelCopy::t('no_intent_match', [], 'web-frontend');
        $this->assertStringNotContainsString('pantalla', mb_strtolower($text));
        $this->assertStringContainsString('opción', mb_strtolower($text));
    }

    public function testWhatsappProfileUsesDedicatedCopy(): void
    {
        $text = AssistantChannelCopy::t('no_intent_match', [], 'whatsapp-paciente');
        $this->assertStringContainsString('menú', mb_strtolower($text));
        $this->assertStringNotContainsString('pantalla', mb_strtolower($text));
    }

    public function testDeepLinkSuffixPlaceholder(): void
    {
        $text = AssistantChannelCopy::t(
            'open_ui_deep_link_suffix',
            ['url' => 'https://example.test/'],
            'whatsapp-paciente'
        );
        $this->assertStringContainsString('https://example.test/', $text);
    }

    public function testProfileKeyFromClientContext(): void
    {
        $this->assertSame('whatsapp_paciente', ClientContextMetadata::profileSectionKeyForAppClient('whatsapp-paciente'));
        $this->assertSame('mobile_paciente', ClientContextMetadata::profileSectionKeyForAppClient('paciente-flutter'));
        $this->assertNull(ClientContextMetadata::profileSectionKeyForAppClient('web-frontend'));
    }
}
