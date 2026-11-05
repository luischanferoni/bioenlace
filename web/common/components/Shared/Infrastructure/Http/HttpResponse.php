<?php

namespace common\components\Shared\Infrastructure\Http;

/**
 * Respuesta HTTP ya decodificada. Inmutable.
 *
 * Existe para no perder headers: el ACL de historia clínica nacional resuelve el
 * `external_id` desde `Location` y con el transporte a mano no podía.
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers claves normalizadas a minúsculas
     * @param array<mixed> $body JSON decodificado, `[]` si el cuerpo no era JSON
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly array $body,
        private readonly string $rawBody
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<mixed> */
    public function body(): array
    {
        return $this->body;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function isOk(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
