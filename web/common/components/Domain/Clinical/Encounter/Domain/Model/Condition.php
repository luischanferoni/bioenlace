<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;

/**
 * Aggregate Condition (diagnóstico / problema / CC).
 *
 * Sin I/O: Application reconstituye, aplica transición y persiste en AR.
 */
final class Condition
{
    /** @var int|null */
    private $id;

    /** @var string */
    private $clinicalStatus;

    /** @var string|null */
    private $note;

    private function __construct(?int $id, string $clinicalStatus, ?string $note)
    {
        $this->id = $id;
        $this->clinicalStatus = strtoupper(trim($clinicalStatus));
        if ($this->clinicalStatus === '') {
            $this->clinicalStatus = ConditionClinicalStatus::UNKNOWN;
        }
        $this->note = $note;
    }

    public static function reconstitute(?int $id, string $clinicalStatus, ?string $note = null): self
    {
        return new self($id, $clinicalStatus, $note);
    }

    public function transitionTo(string $toStatus, ?string $note, \DateTimeImmutable $at): void
    {
        $toStatus = strtoupper(trim($toStatus));
        if (!ConditionClinicalStatus::isValid($toStatus)) {
            throw new \InvalidArgumentException("Estado clínico no válido: {$toStatus}");
        }
        $from = $this->clinicalStatus;
        if (!ConditionClinicalStatus::canTransition($from, $toStatus)) {
            $idLabel = $this->id !== null ? "#{$this->id}" : '(nueva)';
            throw new \InvalidArgumentException(
                "No se puede pasar la condición {$idLabel} de «{$from}» a «{$toStatus}»."
            );
        }
        if ($from === $toStatus) {
            return;
        }

        $this->clinicalStatus = $toStatus;
        $note = $note !== null ? trim($note) : '';
        if ($note !== '') {
            $prefix = '[' . $at->format('Y-m-d H:i') . ' estado→' . $toStatus . '] ';
            $existing = trim((string) ($this->note ?? ''));
            $this->note = $existing === '' ? $prefix . $note : $existing . "\n" . $prefix . $note;
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function clinicalStatus(): string
    {
        return $this->clinicalStatus;
    }

    public function note(): ?string
    {
        return $this->note;
    }
}
