<?php

namespace common\components\Domain\Person\Identity\Application\Service;

final class IdentityVerificationException extends \RuntimeException
{
    public function __construct(public readonly string $verificationStatus)
    {
        parent::__construct(
            'La verificación de identidad no está aprobada por Didit (estado: '
            . $verificationStatus
            . ').'
        );
    }

    public function isRetryable(): bool
    {
        return in_array($this->verificationStatus, ['pending', 'unknown'], true);
    }
}
