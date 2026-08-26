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

    public function testBookingOfferOnlyOnCurrentClinicalMessage(): void
    {
        $this->assertFalse(ChatChannelPolicy::shouldOfferBookingButton('Empezó ayer y se me fue poniendo peor'));
        $this->assertFalse(ChatChannelPolicy::shouldOfferBookingButton(
            'Empezó ayer y se me fue poniendo peor',
            'Tengo fiebre, tos y me duele el cuerpo'
        ));
        $this->assertTrue(ChatChannelPolicy::shouldOfferBookingButton('Tengo fiebre, tos y me duele el cuerpo'));
        $this->assertFalse(ChatChannelPolicy::shouldOfferBookingButton(
            'cómo saco un turno',
            'Tengo fiebre, tos y me duele el cuerpo'
        ));
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

    public function testStudyRequestDetection(): void
    {
        $this->assertTrue(ChatChannelPolicy::isStudyOrPracticeRequest('Necesito una ecografía'));
        $this->assertTrue(ChatChannelPolicy::isExplicitOperationalCareRequest('Necesito una ecografía'));
    }

    public function testNamedPredicateOwnAgenda(): void
    {
        $this->assertTrue(ChatChannelPolicy::namedPredicate(
            'own_agenda_config_edit',
            'necesito modificar mi agenda'
        ));
        $this->assertFalse(ChatChannelPolicy::namedPredicate('unknown_rule', 'hola'));
    }

    public function testCargarMiCoberturaIsStaffOperationalQuery(): void
    {
        $msg = 'Cargar mi cobertura';
        $this->assertTrue(ChatChannelPolicy::isStaffDataAccessOperationalQuery($msg));
    }
}
