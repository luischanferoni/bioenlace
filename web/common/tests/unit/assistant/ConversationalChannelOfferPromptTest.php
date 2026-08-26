<?php

namespace common\tests\unit\assistant;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use common\components\Platform\Assistant\Chat\Channels\Conversational\ChatConversationalConfig;
use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalChannel;

class ConversationalChannelOfferPromptTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        ChatConversationalConfig::resetCacheForTests();
        ChatPreprocessContext::clear();
    }

    protected function _after()
    {
        ChatPreprocessContext::clear();
        ChatConversationalConfig::resetCacheForTests();
    }

    public function testFormatOfferSinOfferDevuelveVacio()
    {
        verify(ConversationalChannel::formatOfferForPrompt(null))->equals('');
    }

    public function testFormatOfferIncluyeSummaryYCapacidades()
    {
        $block = ConversationalChannel::formatOfferForPrompt([
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
            'summary' => 'Te guía según lo que necesitás e incluye centros cercanos.',
            'capabilities' => ['elige_servicio', 'mapa_centros_cercanos'],
        ]);

        verify(str_contains($block, 'Oferta disponible'))->true();
        verify(str_contains($block, 'Solicitar Atención'))->true();
        verify(str_contains($block, 'atencion.necesito-atencion'))->true();
        verify(str_contains($block, 'Te guía según lo que necesitás'))->true();
        verify(str_contains($block, 'elige_servicio'))->true();
        verify(str_contains($block, 'mapa_centros_cercanos'))->true();
    }

    public function testFormatOfferSinSummaryNiCapabilitiesAdvierte()
    {
        $block = ConversationalChannel::formatOfferForPrompt([
            'label' => 'Algo',
            'intent_id' => 'x.y',
            'summary' => '',
            'capabilities' => [],
        ]);

        verify(str_contains($block, 'no declaradas'))->true();
    }

    public function testBookingOfferOriginUsaMensajeActual(): void
    {
        $history = "Tengo fiebre, tos y me duele el cuerpo";
        verify(ConversationalChannel::bookingOfferOriginContent('¿Qué hago con esto?', $history))
            ->equals('¿Qué hago con esto?');
        verify(ConversationalChannel::bookingOfferOriginContent('Me duele la cabeza', $history))
            ->equals('Me duele la cabeza');
    }

    public function testFormatOfferEnContinuacionPriorizaPreguntaActual(): void
    {
        $block = ConversationalChannel::formatOfferForPrompt([
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
            'summary' => 'Te guía según lo que necesitás.',
            'capabilities' => ['urgencia_guardia_info'],
        ], true);

        verify(str_contains($block, 'Conversación en curso'))->true();
        verify(str_contains($block, 'mención breve'))->true();
    }

    public function testBuildPromptSinHistorialNoIncluyeConversacionPrevia(): void
    {
        if (!isset(\Yii::$app)) {
            $this->markTestSkipped('Requiere aplicación Yii.');
        }
        $prompt = ConversationalChannel::buildPrompt('me duele la cabeza', 0, null, '');
        verify(str_contains($prompt, 'Conversación previa'))->false();
        verify(str_contains($prompt, 'Continuación:'))->false();
        verify(str_contains($prompt, 'Mensaje actual del paciente:'))->true();
    }

    public function testBuildPromptConHistorialIncluyeConversacionYContinuacion(): void
    {
        if (!isset(\Yii::$app)) {
            $this->markTestSkipped('Requiere aplicación Yii.');
        }
        $history = "Paciente: me duele la cabeza\nAsistente: Te recomiendo que un profesional te evalúe.";
        $prompt = ConversationalChannel::buildPrompt('¿puedo esperar?', 0, null, $history);

        verify(str_contains($prompt, 'Conversación previa'))->true();
        verify(str_contains($prompt, $history))->true();
        verify(str_contains($prompt, 'Continuación:'))->true();
        verify(str_contains($prompt, 'Historial reciente'))->false();
    }

    public function testFormatPreprocessFactsIncluyeTextoYMenciones(): void
    {
        ChatPreprocessContext::set([
            'normalized_text' => 'me duele la cabeza',
            'user_goal' => 'conversational_clinical',
            'action_text' => '',
            'extractions' => [
                ['span' => 'Hospital Central', 'category' => 'efector', 'synonyms' => []],
            ],
        ]);

        $facts = ConversationalChannel::formatPreprocessFacts();
        verify(str_contains($facts, 'Hechos:'))->true();
        verify(str_contains($facts, 'efector: Hospital Central'))->true();
        verify(str_contains($facts, 'Texto:'))->false();
        verify(str_contains($facts, 'enrut'))->false();
        verify(str_contains($facts, 'canal'))->false();
    }

    public function testFormatPreprocessFactsVacioSinMenciones(): void
    {
        ChatPreprocessContext::set([
            'normalized_text' => 'me duele la cabeza',
            'user_goal' => 'conversational_clinical',
            'action_text' => '',
            'extractions' => [],
        ]);
        verify(ConversationalChannel::formatPreprocessFacts())->equals('');
    }

    public function testFormatPreprocessFactsVacioSinContexto(): void
    {
        verify(ConversationalChannel::formatPreprocessFacts())->equals('');
    }

    public function testStablePromptEsGenericoEstiloHis(): void
    {
        $prompt = ChatConversationalConfig::stablePrompt();
        verify(str_contains($prompt, 'HIS'))->true();
        verify(str_contains($prompt, 'Reglas:'))->true();
        verify(str_contains($prompt, 'Oferta disponible'))->true();
        verify(str_contains($prompt, '{offer_block_title}'))->false();
        verify(str_contains($prompt, 'Primer mensaje'))->false();
    }

    public function testPromptFragmentOfferHeaderUsaTituloCanonico(): void
    {
        $header = ChatConversationalConfig::promptFragment('offer.header', '');
        verify(str_contains($header, 'Oferta disponible'))->true();
        verify(str_contains($header, '{offer_block_title}'))->false();
    }
}
