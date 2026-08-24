<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;

class ChatChannelPolicyTest extends Unit
{
    public function testClinicalSymptomDetection(): void
    {
        $this->assertTrue(ChatChannelPolicy::isClinicalSymptomContent('me duele la cabeza'));
        $this->assertTrue(ChatChannelPolicy::isClinicalSymptomContent('Tengo un pinchazo en el pecho cuando respiro'));
        $this->assertFalse(ChatChannelPolicy::isClinicalSymptomContent('quiero un turno'));
    }

    public function testSymptomGoesConversationalUnlessExplicitTurno(): void
    {
        $this->assertSame(
            'conversational',
            ChatChannelPolicy::resolveUserGoal(
                'estoy con dolor de cabeza, hospital cerca',
                'operational'
            )
        );
        $this->assertSame(
            'operational',
            ChatChannelPolicy::resolveUserGoal('me duele la cabeza y quiero un turno', 'operational')
        );
    }

    public function testBookingOfferAfterSymptomHistory(): void
    {
        $this->assertFalse(ChatChannelPolicy::shouldOfferBookingButton('Empezó ayer y se me fue poniendo peor'));
        $this->assertTrue(ChatChannelPolicy::shouldOfferBookingButton(
            'Empezó ayer y se me fue poniendo peor',
            'Tengo fiebre, tos y me duele el cuerpo'
        ));
        $this->assertTrue(ChatChannelPolicy::shouldOfferBookingButton('Tengo fiebre, tos y me duele el cuerpo'));
    }

    public function testStaffAgendaEditPredicates(): void
    {
        $this->assertTrue(ChatChannelPolicy::suggestsStaffAgendaEdit(
            'necesito modificar las formas de atencion de un profesional'
        ));
        $this->assertFalse(ChatChannelPolicy::suggestsStaffAgendaEdit('crear agenda para un profesional nuevo'));
        $this->assertTrue(ChatChannelPolicy::suggestsOwnAgendaEdit('necesito modificar mi agenda'));
    }

    public function testStaffDataAccessEditExcludesScheduling(): void
    {
        $this->assertTrue(ChatChannelPolicy::isStaffDataAccessEditQuery('modificar agenda del personal'));
        $this->assertFalse(ChatChannelPolicy::isStaffDataAccessEditQuery('modificar turno del paciente'));
    }

    public function testStudyRequestIsOperational(): void
    {
        $this->assertSame('operational', ChatChannelPolicy::heuristicUserGoal('Necesito una ecografía'));
        $this->assertTrue(ChatChannelPolicy::isStudyOrPracticeRequest('Necesito una ecografía'));
    }

    public function testNamedPredicateOwnAgenda(): void
    {
        $this->assertTrue(ChatChannelPolicy::namedPredicate(
            'own_agenda_config_edit',
            'necesito modificar mi agenda'
        ));
        $this->assertFalse(ChatChannelPolicy::namedPredicate('unknown_rule', 'hola'));
    }

    public function testCargarMiCoberturaIsOperationalHeuristic(): void
    {
        $msg = 'Cargar mi cobertura';
        $this->assertTrue(ChatChannelPolicy::isStaffDataAccessOperationalQuery($msg));
        $this->assertSame('operational', ChatChannelPolicy::heuristicUserGoal($msg));
    }
}
