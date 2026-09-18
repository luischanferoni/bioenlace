<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

use common\components\Domain\Clinical\Encounter\Domain\EncounterStatus;

/**
 * Aggregate root Encounter.
 *
 * Sin I/O ni Yii: Application reconstituye desde AR, aplica mutaciones y persiste.
 */
final class Encounter
{
    /** @var EncounterId|null null hasta persistir (alta) */
    private $id;

    /** @var string */
    private $status;

    /** @var \DateTimeImmutable|null */
    private $periodStart;

    /** @var \DateTimeImmutable|null */
    private $periodEnd;

    private function __construct(
        ?EncounterId $id,
        string $status,
        ?\DateTimeImmutable $periodStart,
        ?\DateTimeImmutable $periodEnd
    ) {
        $this->id = $id;
        $this->status = EncounterStatus::normalize($status);
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    public static function open(\DateTimeImmutable $at): self
    {
        return new self(null, EncounterStatus::IN_PROGRESS, $at, null);
    }

    public static function reconstitute(
        EncounterId $id,
        string $status,
        ?\DateTimeImmutable $periodStart,
        ?\DateTimeImmutable $periodEnd
    ): self {
        return new self($id, $status, $periodStart, $periodEnd);
    }

    public function finish(\DateTimeImmutable $at): void
    {
        if ($this->status === EncounterStatus::FINISHED) {
            return;
        }
        if (!EncounterStatus::canTransition($this->status, EncounterStatus::FINISHED)) {
            throw new \InvalidArgumentException(
                "No se puede finalizar un encounter en estado «{$this->status}»."
            );
        }
        $this->status = EncounterStatus::FINISHED;
        $this->periodEnd = $at;
    }

    public function cancel(\DateTimeImmutable $at): void
    {
        if ($this->status === EncounterStatus::CANCELLED) {
            return;
        }
        if (!EncounterStatus::canTransition($this->status, EncounterStatus::CANCELLED)) {
            throw new \InvalidArgumentException(
                "No se puede cancelar un encounter en estado «{$this->status}»."
            );
        }
        $this->status = EncounterStatus::CANCELLED;
        if ($this->periodEnd === null) {
            $this->periodEnd = $at;
        }
    }

    public function id(): ?EncounterId
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function periodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function isInProgress(): bool
    {
        return $this->status === EncounterStatus::IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return $this->status === EncounterStatus::FINISHED;
    }

    public function isTerminal(): bool
    {
        return EncounterStatus::isTerminal($this->status);
    }
}
