<?php

namespace common\components\Domain\Clinical\Encounter\Domain\Model;

/**
 * Identity value object del aggregate {@see Encounter}.
 */
final class EncounterId
{
    /** @var int */
    private $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('EncounterId must be positive.');
        }
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
