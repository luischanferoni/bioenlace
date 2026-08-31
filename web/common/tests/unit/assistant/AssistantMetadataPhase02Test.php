<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Domain\Content\Service\InfoContentAssistantService;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Core\Product\ProductMetadataPaths;

class AssistantMetadataPhase02Test extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
        GuideChannelConfig::resetCacheForTests();
    }

    protected function _after(): void
    {
        ChatPreprocessService::resetCacheForTests();
        GuideChannelConfig::resetCacheForTests();
    }

    public function testPromptYamlFilesExist(): void
    {
        $this->assertFileExists(ProductMetadataPaths::preprocessPromptFile());
        $this->assertFileExists(ProductMetadataPaths::guideChannelFile());
        $this->assertFileExists(ProductMetadataPaths::ambiguousChannelFile());
        $this->assertFileExists(ProductMetadataPaths::assistantChannelCopyFile());
        $this->assertFileExists(ProductMetadataPaths::bookingOfferFile());
        $this->assertFileExists(ProductMetadataPaths::intentFamiliesFile());
        $this->assertFileExists(ProductMetadataPaths::hintResolutionFile());
    }

    public function testPreprocessPromptLoadedFromYaml(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();
        $this->assertStringContainsString('"guide"', $prompt);
        $this->assertStringContainsString('"ambiguous"', $prompt);
        $this->assertStringNotContainsString('{goals_json}', $prompt);
    }

    public function testGuidePromptIncludesPerimetro(): void
    {
        $prompt = GuideChannelConfig::stablePrompt();
        $this->assertStringContainsString('sistema de salud', $prompt);
        $this->assertStringContainsString('context:his', $prompt);
    }

    public function testBookingOfferFromRoutingYaml(): void
    {
        $priority = GuideChannelConfig::bookingOfferIntentPriority();
        $this->assertContains('atencion.necesito-atencion', $priority);
        $this->assertSame('turnos.crear', GuideChannelConfig::bookingOfferIntentPrefixFallback());
    }

    public function testGuideSourceBlock(): void
    {
        $block = GuideChannelConfig::formatSourceBlock('Turnos', 'Podés sacar turno desde el menú.');
        $this->assertStringContainsString('Turnos', $block);
        $this->assertStringContainsString('Podés sacar turno', $block);
    }

    public function testBuildArticlePromptUsesGuideMetadata(): void
    {
        $prompt = InfoContentAssistantService::buildArticlePrompt(
            '¿Cómo cancelo?',
            'Cancelar turno',
            'Entrá a Mis turnos y elegí cancelar.'
        );
        $this->assertStringContainsString('fuente inyectada', strtolower($prompt));
        $this->assertStringContainsString('Cancelar turno', $prompt);
        $this->assertStringContainsString('¿Cómo cancelo?', $prompt);
    }
}
