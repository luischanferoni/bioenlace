<?php

namespace common\components\Shared\Infrastructure\Http\Auth;

/**
 * Token de acceso a un proveedor externo.
 *
 * `invalidate()` existe para que el ACL reintente un `401` sin inventar su propia
 * política de sesión: el base no conoce tokens.
 */
interface AccessTokenProvider
{
    public function token(): string;

    public function invalidate(): void;
}
