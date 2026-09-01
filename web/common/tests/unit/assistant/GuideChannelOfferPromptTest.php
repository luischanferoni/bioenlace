<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;

class GuideChannelOfferPromptTest extends Unit
{
    protected function _before()
    {
        GuideChannelConfig::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    protected function _after()
    {
        GuideChannelConfig::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    public function testFormatCtaDetailsNull(): void
    {
        verify(GuideChannel::formatCtaDetailsForPrompt(null))->equals('');
    }

    public function testFormatCtaDetailsWithCapabilities(): void
    {
        $block = GuideChannel::formatCtaDetailsForPrompt([
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
            'summary' => 'Pedir atención',
            'capabilities' => ['confirmar'],
        ]);
        verify($block)->stringContainsString('Solicitar Atención');
        verify($block)->stringContainsString('confirmar');
    }

    public function testBookingOfferOriginFromHistory(): void
    {
        $history = "Paciente: Me duele la cabeza\nAsistente: ¿Desde cuándo?";
        verify(GuideChannel::bookingOfferOriginContent('¿Qué hago con esto?', $history))
            ->equals('Me duele la cabeza');
        verify(GuideChannel::bookingOfferOriginContent('Me duele la cabeza', $history))
            ->equals('Me duele la cabeza');
    }

    public function testBuildPromptIncludesStableAndMessage(): void
    {
        $prompt = GuideChannel::buildPrompt('me duele la cabeza', 0, '', null);
        verify($prompt)->stringContainsString('me duele la cabeza');
        verify($prompt)->stringContainsString('Información Hospitalaria');
    }

    public function testFormatPreprocessFactsLines(): void
    {
        ChatPreprocessContext::set([
            'ok' => true,
            'normalized_text' => 'me duele la cabeza',
            'user_goal' => 'guide',
            'action_text' => 'pedir turno',
            'extractions' => [
                ['span' => 'cabeza', 'category' => 'turno', 'synonyms' => []],
            ],
            'context_areas' => [],
        ]);

        $facts = GuideChannel::formatPreprocessFactsLines();
        verify($facts)->stringContainsString('cabeza');
        verify($facts)->stringContainsString('pedir turno');
    }

    public function testStablePromptFromGuideYaml(): void
    {
        $template = GuideChannelConfig::stablePrompt();
        verify($template)->stringContainsString('Información Hospitalaria');
        verify($template)->stringContainsString('{current_message}');
        verify($template)->stringContainsString('Mensaje actual del usuario');
    }
}
