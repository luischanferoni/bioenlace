<?php

namespace common\components\Shared\Infrastructure\Http\Auth;

/** Token fijo de configuración, para proveedores con API key y sin OAuth. */
final class StaticTokenProvider implements AccessTokenProvider
{
    public function __construct(private readonly string $token)
    {
    }

    public function token(): string
    {
        return $this->token;
    }

    public function invalidate(): void
    {
    }
}
