<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\Clinical\Encounter;
use common\models\ProfesionalCobertura;

class AgendaByEncounterClassTest extends Unit
{
    protected function _before(): void
    {
        AgendaByEncounterClassMetadata::reset();
    }

    public function testPatientSlotFinderIsAmbOnly(): void
    {
        $classes = AgendaByEncounterClassMetadata::patientSlotFinderClasses();
        $this->assertSame([Encounter::ENCOUNTER_CLASS_AMB], $classes);
        $this->assertTrue(AgendaByEncounterClassMetadata::isPatientBookingClass(Encounter::ENCOUNTER_CLASS_AMB));
        $this->assertFalse(AgendaByEncounterClassMetadata::isPatientBookingClass(Encounter::ENCOUNTER_CLASS_EMER));
        $this->assertFalse(AgendaByEncounterClassMetadata::isPatientBookingClass(Encounter::ENCOUNTER_CLASS_IMP));
    }

    public function testCoberturaClasses(): void
    {
        $classes = AgendaByEncounterClassMetadata::coberturaClasses();
        $this->assertContains(Encounter::ENCOUNTER_CLASS_EMER, $classes);
        $this->assertContains(Encounter::ENCOUNTER_CLASS_IMP, $classes);
        $this->assertTrue(AgendaByEncounterClassMetadata::isCoberturaClass(Encounter::ENCOUNTER_CLASS_EMER));
        $this->assertFalse(AgendaByEncounterClassMetadata::isCoberturaClass(Encounter::ENCOUNTER_CLASS_AMB));
    }

    public function testCoberturaRejectsAmbClass(): void
    {
        $model = new ProfesionalCobertura();
        $model->id_persona = 1;
        $model->id_efector = 1;
        $model->encounter_class = Encounter::ENCOUNTER_CLASS_AMB;
        $model->inicio = '2026-07-10 08:00:00';
        $model->fin = '2026-07-10 16:00:00';
        $this->assertFalse($model->validate(['encounter_class']));
        $this->assertArrayHasKey('encounter_class', $model->errors);
    }

    public function testCoberturaRequiresFinAfterInicio(): void
    {
        $model = new ProfesionalCobertura();
        $model->id_persona = 1;
        $model->id_efector = 1;
        $model->encounter_class = Encounter::ENCOUNTER_CLASS_EMER;
        $model->inicio = '2026-07-10 16:00:00';
        $model->fin = '2026-07-10 08:00:00';
        $this->assertFalse($model->validate(['fin']));
        $this->assertArrayHasKey('fin', $model->errors);
    }

    public function testCoberturaVsAmbSlotsEnabled(): void
    {
        $conflicts = AgendaByEncounterClassMetadata::loadConfig()['conflicts'] ?? [];
        $this->assertTrue((bool) ($conflicts['cobertura_vs_amb_slots'] ?? false));
    }
}
