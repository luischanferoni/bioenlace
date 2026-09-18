<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Domain\EncounterStatus;
use common\components\Domain\Clinical\Encounter\Domain\Model\ChiefComplaintReason;
use common\components\Domain\Clinical\Encounter\Domain\Model\Condition;
use common\components\Domain\Clinical\Encounter\Domain\Model\Encounter;
use common\components\Domain\Clinical\Encounter\Domain\Model\EncounterId;

/**
 * Reglas puras Domain/Model Encounter (fase 5) — sin DB.
 */
class EncounterDomainModelTest extends Unit
{
    public function testEncounterStatusTransitions(): void
    {
        $this->assertTrue(EncounterStatus::canTransition(EncounterStatus::IN_PROGRESS, EncounterStatus::FINISHED));
        $this->assertTrue(EncounterStatus::canTransition(EncounterStatus::PLANNED, EncounterStatus::CANCELLED));
        $this->assertFalse(EncounterStatus::canTransition(EncounterStatus::FINISHED, EncounterStatus::IN_PROGRESS));
        $this->assertTrue(EncounterStatus::isTerminal(EncounterStatus::CANCELLED));
        $this->assertTrue(EncounterStatus::isOpen(EncounterStatus::IN_PROGRESS));
    }

    public function testEncounterOpenFinishCancel(): void
    {
        $at = new \DateTimeImmutable('2026-09-17 10:00:00');
        $opened = Encounter::open($at);
        $this->assertSame(EncounterStatus::IN_PROGRESS, $opened->status());
        $this->assertTrue($opened->isInProgress());
        $this->assertNull($opened->id());

        $enc = Encounter::reconstitute(
            new EncounterId(42),
            EncounterStatus::IN_PROGRESS,
            $at,
            null
        );
        $enc->finish(new \DateTimeImmutable('2026-09-17 11:00:00'));
        $this->assertTrue($enc->isFinished());
        $this->assertSame('2026-09-17 11:00:00', $enc->periodEnd()->format('Y-m-d H:i:s'));

        // Idempotente
        $enc->finish(new \DateTimeImmutable('2026-09-17 12:00:00'));
        $this->assertSame('2026-09-17 11:00:00', $enc->periodEnd()->format('Y-m-d H:i:s'));
    }

    public function testEncounterFinishFromCancelledThrows(): void
    {
        $enc = Encounter::reconstitute(
            new EncounterId(1),
            EncounterStatus::CANCELLED,
            new \DateTimeImmutable('2026-09-17 10:00:00'),
            new \DateTimeImmutable('2026-09-17 10:05:00')
        );
        $this->expectException(\InvalidArgumentException::class);
        $enc->finish(new \DateTimeImmutable('2026-09-17 11:00:00'));
    }

    public function testChiefComplaintReasonFromTextAndCoded(): void
    {
        $text = ChiefComplaintReason::fromInput('  Cefalea  ');
        $this->assertNotNull($text);
        $this->assertNull($text->code());
        $this->assertSame(ChiefComplaintReason::UNCODED_SYSTEM, $text->codeSystem());
        $this->assertSame('Cefalea', $text->display());

        $coded = ChiefComplaintReason::fromInput([
            'codigo' => '25064002',
            'display' => 'Cefalea',
        ]);
        $this->assertNotNull($coded);
        $this->assertSame('25064002', $coded->code());
        $this->assertSame(ChiefComplaintReason::DEFAULT_CODED_SYSTEM, $coded->codeSystem());

        $this->assertNull(ChiefComplaintReason::fromInput(''));
        $this->assertNull(ChiefComplaintReason::fromInput([]));
    }

    public function testConditionTransitionAppendsNote(): void
    {
        $c = Condition::reconstitute(7, ConditionClinicalStatus::ACTIVE, null);
        $c->transitionTo(
            ConditionClinicalStatus::RESOLVED,
            'alta clínica',
            new \DateTimeImmutable('2026-09-17 15:30:00')
        );
        $this->assertSame(ConditionClinicalStatus::RESOLVED, $c->clinicalStatus());
        $this->assertStringContainsString('estado→RESOLVED', (string) $c->note());
        $this->assertStringContainsString('alta clínica', (string) $c->note());
    }

    public function testConditionInvalidTransitionThrows(): void
    {
        $c = Condition::reconstitute(1, ConditionClinicalStatus::RESOLVED, null);
        $this->expectException(\InvalidArgumentException::class);
        $c->transitionTo(
            ConditionClinicalStatus::INACTIVE,
            null,
            new \DateTimeImmutable('now')
        );
    }
}
