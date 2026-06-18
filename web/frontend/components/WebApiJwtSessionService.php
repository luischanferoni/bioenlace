<?php

namespace frontend\components;

use common\models\Person\Persona;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Yii;
use yii\web\IdentityInterface;

/**
 * JWT de API v1 en sesión web: emisión y renovación mientras la sesión Yii sigue válida.
 */
final class WebApiJwtSessionService
{
    private const SESSION_KEY = 'apiJwtToken';

    /** Renovar si faltan menos de 5 minutos para expirar. */
    private const REFRESH_BEFORE_EXPIRY_SECONDS = 300;

    private const TTL_SECONDS = 86400;

    public static function ensureValidTokenInSession(): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }

        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            return;
        }

        $existing = Yii::$app->session->get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '' && !self::shouldRefreshToken($existing)) {
            return;
        }

        $persona = Persona::findOne(['id_user' => $identity->id]);
        if ($persona === null) {
            return;
        }

        self::storeTokenForIdentity($identity, $persona);
    }

    public static function storeTokenForIdentity(IdentityInterface $identity, Persona $persona): void
    {
        Yii::$app->session->set(self::SESSION_KEY, self::encodeToken($identity, $persona));
    }

    private static function encodeToken(IdentityInterface $identity, Persona $persona): string
    {
        $now = time();
        $payload = [
            'user_id' => $identity->id,
            'email' => $identity->email,
            'id_persona' => (int) $persona->id_persona,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ];

        return JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');
    }

    private static function shouldRefreshToken(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key(Yii::$app->params['jwtSecret'], 'HS256'));
            $exp = isset($decoded->exp) ? (int) $decoded->exp : 0;
            if ($exp <= 0) {
                return true;
            }

            return $exp <= (time() + self::REFRESH_BEFORE_EXPIRY_SECONDS);
        } catch (\Throwable $e) {
            return true;
        }
    }
}
