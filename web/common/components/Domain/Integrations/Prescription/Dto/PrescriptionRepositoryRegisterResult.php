<?php

namespace common\components\Domain\Integrations\Prescription\Dto;

/**
 * Resultado de intentar registrar la receta en el repositorio nacional.
 */
final class PrescriptionRepositoryRegisterResult
{
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $connectorKey,
        public readonly ?string $repositoryId = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function skipped(string $connectorKey, string $reason): self
    {
        return new self(self::STATUS_SKIPPED, $connectorKey, null, $reason);
    }

    public static function success(string $connectorKey, string $repositoryId, ?string $message = null): self
    {
        return new self(self::STATUS_SUCCESS, $connectorKey, $repositoryId, $message);
    }

    public static function failed(string $connectorKey, string $message): self
    {
        return new self(self::STATUS_FAILED, $connectorKey, null, $message);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'connectorKey' => $this->connectorKey,
            'repositoryId' => $this->repositoryId,
            'message' => $this->message,
        ];
    }
}
