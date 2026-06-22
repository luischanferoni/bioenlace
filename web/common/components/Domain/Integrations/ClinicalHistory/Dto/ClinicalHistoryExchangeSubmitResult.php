<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Dto;

/**
 * Resultado de intentar enviar un Bundle al receptor nacional.
 */
final class ClinicalHistoryExchangeSubmitResult
{
    private function __construct(
        public readonly bool $success,
        public readonly bool $skipped,
        public readonly ?string $externalId,
        public readonly ?string $message,
        public readonly bool $retryable
    ) {
    }

    public static function sent(string $externalId, ?string $message = null): self
    {
        return new self(true, false, $externalId, $message, false);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, true, null, $reason, false);
    }

    public static function failed(string $message, bool $retryable = true): self
    {
        return new self(false, false, null, $message, $retryable);
    }
}
