<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ReservaTurnoTriageFlowDraftHydrator;

class ReservaTurnoTriageFlowDraftHydratorTest extends Unit
{
    public function testHidrataZonaGeneralDesdeSaludMental(): void
    {
        $body = [
            'content' => 'Estoy muy ansioso y no puedo dormir',
            'draft' => [],
        ];

        ReservaTurnoTriageFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame('malestar_nuevo', $body['draft']['triage_raiz'] ?? null);
        $this->assertSame('zona_general', $body['draft']['triage_zona'] ?? null);
    }

    public function testNoPisaZonaYaElegida(): void
    {
        $body = [
            'content' => 'Estoy muy ansioso y no puedo dormir',
            'draft' => [
                'triage_raiz' => 'malestar_nuevo',
                'triage_zona' => 'zona_pecho',
            ],
        ];

        ReservaTurnoTriageFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame('zona_pecho', $body['draft']['triage_zona'] ?? null);
    }

    public function testHidrataZonaDesdeHistorialSiElMensajeEsFollowUp(): void
    {
        $body = [
            'content' => '¿Qué hago con esto?',
            '_patient_history' => "Tengo fiebre, tos y me duele el cuerpo",
            'draft' => [],
        ];

        ReservaTurnoTriageFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame('malestar_nuevo', $body['draft']['triage_raiz'] ?? null);
        $this->assertSame('zona_pecho', $body['draft']['triage_zona'] ?? null);
    }

    public function testNoUsaHistorialSiElMensajePideEstudio(): void
    {
        $body = [
            'content' => 'Necesito una ecografía',
            '_patient_history' => "Tengo fiebre, tos y me duele el cuerpo",
            'draft' => [],
        ];

        ReservaTurnoTriageFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertNotSame('malestar_nuevo', $body['draft']['triage_raiz'] ?? null);
    }
}
