<?php

namespace common\tests\unit\assistant;

use common\components\Platform\Assistant\Chat\Channels\Conversational\ConversationalChannel;

class ConversationalChannelOfferPromptTest extends \Codeception\Test\Unit
{
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

    public function testBookingOfferOriginUsaSintomaDelHistorial(): void
    {
        $history = "Tengo fiebre, tos y me duele el cuerpo";
        verify(ConversationalChannel::bookingOfferOriginContent('¿Qué hago con esto?', $history))
            ->equals('Tengo fiebre, tos y me duele el cuerpo');
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

        verify(str_contains($block, 'respondé primero'))->true();
        verify(str_contains($block, 'No reinicies empatía'))->true();
    }
}
