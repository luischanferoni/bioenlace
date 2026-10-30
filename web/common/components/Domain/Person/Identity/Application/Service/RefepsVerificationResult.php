<?php

namespace common\components\Domain\Person\Identity\Application\Service;

final class RefepsVerificationResult
{
    public const CONFIRMED = 'confirmed';
    public const NOT_FOUND = 'not_found';
    public const UNAVAILABLE = 'unavailable';
    public const REVIEW_REQUIRED = 'review_required';

    public function __construct(
        public readonly string $status,
        public readonly string $message
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::CONFIRMED;
    }
}
