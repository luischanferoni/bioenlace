<?php

namespace common\components\Services\Assistant;

/**
 * Contexto para resolver hints: intent operativo, draft acumulado y usuario.
 */
final class HintResolutionContext
{
    public string $intentId;

    public int $userId;

    /** @var array<string, mixed> */
    public array $draft;

    /**
     * @param array<string, mixed> $draft
     */
    public function __construct(string $intentId, int $userId, array $draft = [])
    {
        $this->intentId = trim($intentId);
        $this->userId = $userId;
        $this->draft = $draft;
    }

    public function draftInt(string $key): int
    {
        if (!array_key_exists($key, $this->draft)) {
            return 0;
        }
        $v = $this->draft[$key];

        return is_numeric($v) ? (int) $v : 0;
    }

    public function draftString(string $key): string
    {
        if (!array_key_exists($key, $this->draft)) {
            return '';
        }

        return trim((string) $this->draft[$key]);
    }
}
