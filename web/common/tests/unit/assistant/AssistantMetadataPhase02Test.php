<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Domain\Content\Service\InfoContentAssistantService;
use common\components\Platform\Assistant\Chat\Channels\Conversational\ChatConversationalConfig;
use common\components\Platform\Assistant\Chat\Channels\Informational\InformationalChannelConfig;
use common\components\Platform\Assistant\Chat\Preprocess\ChatPreprocessService;
use common\components\Platform\Core\Product\ProductMetadataPaths;

class AssistantMetadataPhase02Test extends Unit
{
    protected function _before(): void
    {
        ChatPreprocessService::resetCacheForTests();
        ChatConversationalConfig::resetCacheForTests();
        InformationalChannelConfig::resetCacheForTests();
    }

    protected function _after(): void
    {
        ChatPreprocessService::resetCacheForTests();
        ChatConversationalConfig::resetCacheForTests();
        InformationalChannelConfig::resetCacheForTests();
    }

    public function testPromptYamlFilesExist(): void
    {
        $this->assertFileExists(ProductMetadataPaths::preprocessPromptFile());
        $this->assertFileExists(ProductMetadataPaths::conversationalChannelFile());
        $this->assertFileExists(ProductMetadataPaths::informationalConversationalFile());
        $this->assertFileExists(ProductMetadataPaths::ambiguousConversationalFile());
        $this->assertFileExists(ProductMetadataPaths::assistantChannelCopyFile());
        $this->assertFileExists(ProductMetadataPaths::bookingOfferFile());
        $this->assertFileExists(ProductMetadataPaths::intentFamiliesFile());
        $this->assertFileExists(ProductMetadataPaths::hintResolutionFile());
    }

    public function testPreprocessPromptLoadedFromYaml(): void
    {
        $prompt = ChatPreprocessService::stablePromptPrefix();
        $this->assertStringContainsString('conversational_clinical', $prompt);
        $this->assertStringContainsString('ambiguous_conversational', $prompt);
        $this->assertStringNotContainsString('{goals_json}', $prompt);
    }

    public function testClinicalPromptIncludesPerimetro(): void
    {
        $prompt = ChatConversationalConfig::stablePrompt();
        $this->assertStringContainsString('Perímetro', $prompt);
        $this->assertStringContainsString('HIS', $prompt);
    }

    public function testBookingOfferFromRoutingYaml(): void
    {
        $priority = ChatConversationalConfig::bookingOfferIntentPriority();
        $this->assertContains('atencion.necesito-atencion', $priority);
        $this->assertSame('turnos.crear', ChatConversationalConfig::bookingOfferIntentPrefixFallback());
    }

    public function testInformationalPromptAnchoredToSource(): void
    {
        $prompt = InformationalChannelConfig::stablePrompt();
        $this->assertStringContainsString('SOLO la fuente inyectada', $prompt);

        $block = InformationalChannelConfig::formatSourceBlock('Turnos', 'Podés sacar turno desde el menú.');
        $this->assertStringContainsString('Turnos', $block);
        $this->assertStringContainsString('Podés sacar turno', $block);
    }

    public function testBuildArticlePromptUsesInformationalMetadata(): void
    {
        $prompt = InfoContentAssistantService::buildArticlePrompt(
            '¿Cómo cancelo?',
            'Cancelar turno',
            'Entrá a Mis turnos y elegí cancelar.'
        );
        $this->assertStringContainsString('SOLO la fuente inyectada', $prompt);
        $this->assertStringContainsString('Cancelar turno', $prompt);
        $this->assertStringContainsString('¿Cómo cancelo?', $prompt);
    }
}
