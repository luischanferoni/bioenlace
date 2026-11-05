<?php

namespace common\components\Shared\Infrastructure\Http;

/**
 * Reintentos acotados.
 *
 * Regla dura: sólo se reintenta un método idempotente o una request con clave de
 * idempotencia (que se repite en cada intento, es lo que la hace segura). Un `POST`
 * sin clave nunca se repite.
 */
final class RetryPolicy
{
    public const DEFAULT_MAX_ATTEMPTS = 2;

    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

    /** @var callable(int): void */
    private $sleeper;

    /**
     * @param int $maxAttempts reintentos, no intentos totales
     * @param (callable(int): void)|null $sleeper recibe milisegundos; inyectable para test
     */
    public function __construct(
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $baseDelayMs = 200,
        private readonly int $maxDelayMs = 2000,
        ?callable $sleeper = null
    ) {
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /** @param int $failedAttempt número 1-based del intento que acaba de fallar */
    public function shouldRetry(
        string $method,
        bool $hasIdempotencyKey,
        ?HttpApiException $error,
        int $failedAttempt
    ): bool {
        if ($error === null || !$error->isRetryable()) {
            return false;
        }
        if ($failedAttempt > $this->maxAttempts) {
            return false;
        }

        return $this->isIdempotent($method) || $hasIdempotencyKey;
    }

    public function delayMs(int $failedAttempt): int
    {
        $exponential = $this->baseDelayMs * (2 ** max(0, $failedAttempt - 1));

        return (int) min($this->maxDelayMs, $exponential);
    }

    public function waitBeforeRetry(int $failedAttempt): void
    {
        $delay = $this->delayMs($failedAttempt);
        if ($delay > 0) {
            ($this->sleeper)($delay);
        }
    }

    private function isIdempotent(string $method): bool
    {
        return in_array(strtoupper($method), self::IDEMPOTENT_METHODS, true);
    }
}
