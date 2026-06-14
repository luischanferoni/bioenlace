<?php

namespace common\components\Integrations\Mpi;

use Firebase\JWT\JWT;
use Yii;

/**
 * JWT HS512 para integración MPI/SEIPA (reemplaza sizeg/yii2-jwt + lcobucci).
 */
final class MpiJwtTokenService
{
    public static function buildBearerToken(string $issuer, string $audience, int $ttlSeconds = 15): string
    {
        $secret = (string) (Yii::$app->params['jwtSecret'] ?? '');
        if ($secret === '') {
            throw new \RuntimeException('jwtSecret no configurado en params.');
        }

        $now = time();

        return JWT::encode([
            'iss' => $issuer,
            'aud' => $audience,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'id_cliente' => 'sisse',
        ], $secret, 'HS512');
    }
}
