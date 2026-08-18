<?php

namespace common\tests\unit\scheduling;

use common\components\Domain\Scheduling\Service\TurnosVerMisTurnosFlowDraftHydrator;

class TurnosVerMisTurnosFlowDraftHydratorTest extends \Codeception\Test\Unit
{
    public function testContentOfferTermsExtraeMencionYOmiteMuletillas()
    {
        $terms = TurnosVerMisTurnosFlowDraftHydrator::contentOfferTerms(
            'Decime cuándo fue la última vez que fui al dentista'
        );

        verify($terms)->contains('dentista');
        verify(in_array('ultima', $terms, true))->false();
        verify(in_array('fui', $terms, true))->false();
    }

    public function testFilterByServicioId()
    {
        $turnos = [
            ['id' => 1, 'id_servicio_asignado' => 10, 'servicio' => 'Odontología'],
            ['id' => 2, 'id_servicio_asignado' => 20, 'servicio' => 'Clínica'],
        ];

        $out = TurnosVerMisTurnosFlowDraftHydrator::filterByServicioId($turnos, 10);

        verify(count($out))->equals(1);
        verify($out[0]['id'])->equals(1);
    }

    public function testHeaderUltimoEnOfertaCruzoYNoCruzo()
    {
        $conMatch = TurnosVerMisTurnosFlowDraftHydrator::headerUltimoEnOferta(
            true,
            'Odontología',
            ['• Lunes · Odontología']
        );
        verify($conMatch)->stringContainsString('La última vez en Odontología');

        $sinMatch = TurnosVerMisTurnosFlowDraftHydrator::headerUltimoEnOferta(
            false,
            '',
            ['• Lunes · Clínica']
        );
        verify($sinMatch)->stringContainsString('No encontré esa última visita');
        verify($sinMatch)->stringContainsString('turnos más recientes');
        verify(str_contains($sinMatch, 'Lunes · Clínica'))->false();
        verify(str_contains($sinMatch, '•'))->false();
    }
}
