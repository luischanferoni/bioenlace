<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Service\AppointmentReasonQuestionnaireCatalogService;
use common\components\Domain\Clinical\Encounter\Domain\AppointmentReasonQuestionnaireCatalog;

final class AppointmentReasonQuestionnaireCatalogServiceTest extends Unit
{
    protected function _after(): void
    {
        AppointmentReasonQuestionnaireCatalogService::resetCacheForTests();
    }

    public function testCatalogoHabilitadoConCierreAcotado(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $this->assertTrue($catalog->isEnabled());
        $this->assertNotSame([], $catalog->questions());
        $this->assertSame(
            AppointmentReasonQuestionnaireCatalog::CLOSING_MAX_LENGTH,
            $catalog->closingMaxLength()
        );
        $this->assertSame(
            AppointmentReasonQuestionnaireCatalog::CLOSING_ID,
            $catalog->closing()['id']
        );
    }

    public function testSoloDevuelvePreguntasDeLaZonaDelTriage(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $ids = $this->ids($catalog->pendingQuestions(['triage_zona' => 'zona_pecho'], []));

        $this->assertContains('pecho_esfuerzo', $ids);
        $this->assertNotContains('cabeza_alarma', $ids);
        $this->assertNotContains('estudio_orden', $ids);
    }

    public function testPreguntaDeRaizApareceSoloParaEseMotivoDeReserva(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $porEstudio = $this->ids($catalog->pendingQuestions(['triage_raiz' => 'estudio_pedido'], []));
        $porMalestar = $this->ids($catalog->pendingQuestions(['triage_raiz' => 'malestar_nuevo'], []));

        $this->assertContains('estudio_orden', $porEstudio);
        $this->assertNotContains('estudio_orden', $porMalestar);
    }

    public function testNoRepiteLoQueElTriageYaRespondio(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $ids = $this->ids($catalog->pendingQuestions([
            'triage_zona' => 'zona_pecho',
            'triage_evolucion' => 'empeoro',
            'triage_alarmas' => 'ninguna',
        ], []));

        $this->assertNotContains('evolucion', $ids);
        $this->assertNotContains('alarma', $ids);
        $this->assertContains('inicio', $ids);
    }

    public function testOmitePreguntasYaRespondidasEnElCuestionario(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $ids = $this->ids($catalog->pendingQuestions([], ['inicio', 'intensidad']));

        $this->assertNotContains('inicio', $ids);
        $this->assertNotContains('intensidad', $ids);
        $this->assertContains('medicacion', $ids);
    }

    public function testOtraSoloExisteCuandoLaPreguntaLaHabilita(): void    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $conOtra = $catalog->questionById('medicacion');
        $sinOtra = $catalog->questionById('intensidad');

        $this->assertNotNull($conOtra);
        $this->assertNotNull($sinOtra);
        $this->assertSame(
            AppointmentReasonQuestionnaireCatalog::OTHER_OPTION_ID,
            $catalog->resolveOption($conOtra, 'otra')['id'] ?? null
        );
        $this->assertNull($catalog->resolveOption($sinOtra, 'otra'));
        $this->assertNull($catalog->resolveOption($sinOtra, 'no_existe'));
    }

    public function testPermiteReescribirUnaRespuestaYaGuardada(): void
    {
        $catalog = new AppointmentReasonQuestionnaireCatalogService();

        $inicio = $catalog->questionById('inicio');
        $evolucion = $catalog->questionById('evolucion');

        $this->assertNotNull($inicio);
        $this->assertNotNull($evolucion);
        $this->assertTrue($catalog->appliesToTriage($inicio, []));
        $this->assertFalse($catalog->appliesToTriage($evolucion, ['triage_evolucion' => 'empeoro']));
    }

    /**
     * @param list<array<string, mixed>> $questions
     * @return list<string>
     */
    private function ids(array $questions): array
    {
        return array_map(static fn (array $q): string => (string) $q['id'], $questions);
    }
}
