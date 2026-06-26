<?php

namespace common\components\Domain\Scheduling\Service;

use Yii;

/**
 * Token firmado para página pública de reubicación (sin sesión).
 */
final class TurnoResolucionLinkTokenService
{
    public function issue(int $idResolucion, int $idPersona, int $ttlSeconds): string
    {
        $exp = time() + max(60, $ttlSeconds);
        $payload = $idResolucion . ':' . $idPersona . ':' . $exp;
        $signed = Yii::$app->security->hashData($payload, $this->signingKey());

        return $this->urlSafeEncode($signed);
    }

    /**
     * @return array{id_resolucion: int, id_persona: int}|null
     */
    public function verify(string $token): ?array
    {
        $raw = $this->urlSafeDecode(trim($token));
        if ($raw === '') {
            return null;
        }

        $payload = Yii::$app->security->validateData($raw, $this->signingKey());
        if ($payload === false || !is_string($payload)) {
            return null;
        }

        $parts = explode(':', $payload);
        if (count($parts) !== 3) {
            return null;
        }

        $exp = (int) $parts[2];
        if ($exp < time()) {
            return null;
        }

        return [
            'id_resolucion' => (int) $parts[0],
            'id_persona' => (int) $parts[1],
        ];
    }

    public function buildPublicUrl(string $token): string
    {
        $mc = Yii::$app->params['turnoResolucionMulticanal'] ?? [];
        $base = isset($mc['public_base_url']) ? trim((string) $mc['public_base_url']) : '';
        if ($base !== '') {
            return rtrim($base, '/') . '/turno/resolucion/' . rawurlencode($token);
        }

        if (Yii::$app->has('urlManager')) {
            $um = Yii::$app->urlManager;
            if ($um instanceof \yii\web\UrlManager) {
                return $um->createAbsoluteUrl(['turno-public/resolucion', 'token' => $token]);
            }
        }

        return '/turno/resolucion/' . rawurlencode($token);
    }

    private function signingKey(): string
    {
        $mc = Yii::$app->params['turnoResolucionMulticanal'] ?? [];
        $key = isset($mc['signing_key']) ? trim((string) $mc['signing_key']) : '';
        if ($key !== '') {
            return $key;
        }

        return hash('sha256', (string) Yii::$app->id . '-turno-resolucion-multicanal');
    }

    private function urlSafeEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function urlSafeDecode(string $token): string
    {
        $pad = strlen($token) % 4;
        if ($pad > 0) {
            $token .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
