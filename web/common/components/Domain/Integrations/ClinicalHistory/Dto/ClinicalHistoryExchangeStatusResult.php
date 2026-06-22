<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Dto;

/**
 * Resultado de consultar estado de un envío en el receptor nacional.
 */
final class ClinicalHistoryExchangeStatusResult
{
    private function __construct(
        public readonly bool $supported,
        public readonly bool $found,
        public readonly ?string $externalId,
        public readonly ?string $status,
        public readonly ?string $message
    ) {
    }

    public static function unsupported(): self
    {
        return new self(false, false, null, null, null);
    }

    public static function notFound(?string $message = null): self
    {
        return new self(true, false, null, null, $message);
    }

    public static function found(string $externalId, ?string $status = null, ?string $message = null): self
    {
        return new self(true, true, $externalId, $status, $message);
    }
}
