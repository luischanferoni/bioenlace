<?php

namespace common\components\Shared\Infrastructure\Http;

/**
 * Falla contra un proveedor externo: transporte (`statusCode` 0) o respuesta no-2xx.
 *
 * El mensaje conserva el texto del proveedor. El ACL decide si lo muestra o lo traduce;
 * el base no transforma un error de negocio en éxito.
 */
final class HttpApiException extends \RuntimeException
{
    /** @param list<string> $providerCauses */
    public function __construct(
        private readonly string $provider,
        private readonly int $statusCode,
        string $message,
        private readonly array $providerCauses = [],
        ?\Throwable $previous = null,
        private readonly ?string $responseBody = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /** Slug del proveedor para logs (`mercadopago`, `sianlabs`, …). */
    public function provider(): string
    {
        return $this->provider;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return list<string> */
    public function providerCauses(): array
    {
        return $this->providerCauses;
    }

    /**
     * Cuerpo crudo de la respuesta fallida. Existe para que un ACL pueda registrar el
     * detalle que el proveedor devolvió sin volver a hacer la request.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }

    public function isRetryable(): bool
    {
        return $this->statusCode === 0
            || $this->statusCode === 408
            || $this->statusCode === 429
            || $this->statusCode >= 500;
    }
}
