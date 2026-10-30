<?php

namespace common\components\Domain\Person\Identity\Application\Service;

final class RefepsVerificationException extends \DomainException
{
    public function __construct(
        public readonly string $verificationStatus,
        string $message
    ) {
        parent::__construct($message);
    }
}
