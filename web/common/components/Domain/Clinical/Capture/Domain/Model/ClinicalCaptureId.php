<?php

namespace common\components\Domain\Clinical\Capture\Domain\Model;

/**
 * Identity del aggregate ClinicalCapture.
 */
final class ClinicalCaptureId
{
    /** @var int */
    private $value;

    private function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('ClinicalCaptureId debe ser positivo.');
        }
        $this->value = $value;
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
