<?php

namespace frontend\components;

use common\models\Platform\User;
use Yii;
use yii\db\Query;
use yii\web\BadRequestHttpException;

/**
 * Aprobación QR de login web desde Personal de Salud.
 *
 * El navegador crea un desafío de un solo uso y muestra un QR. La app móvil (usuario
 * autenticado con un rol habilitado en ambos canales) lo escanea y lo aprueba. El
 * navegador consulta el estado y, al estar aprobado, inicia la sesión web del usuario.
 */
final class QrLoginService
{
    private const TTL_SECONDS = 120;

    public static function start(string $webSessionId): array
    {
        $challengeId = bin2hex(random_bytes(32));
        $now = time();

        Yii::$app->db->createCommand()->insert('{{%auth_challenge}}', [
            'id' => $challengeId,
            'id_user' => null,
            'purpose' => 'qr-login',
            'challenge_hash' => hash('sha256', $challengeId),
            'challenge' => $challengeId,
            'session_hash' => hash('sha256', $webSessionId),
            'rp_id' => (string) Yii::$app->request->getHostInfo(),
            'origin' => (string) Yii::$app->request->getHostInfo(),
            'state' => 'pending',
            'approved_user_id' => null,
            'expires_at' => $now + self::TTL_SECONDS,
            'consumed_at' => null,
            'created_at' => $now,
        ])->execute();

        return [
            'challengeId' => $challengeId,
            'expiresAt' => $now + self::TTL_SECONDS,
            'qrPayload' => self::qrPayload($challengeId),
        ];
    }

    public static function approve(string $challengeId, User $mobileUser): void
    {
        if ((int) $mobileUser->status !== User::STATUS_ACTIVE) {
            throw new BadRequestHttpException('La cuenta no está habilitada para aprobar un acceso web.');
        }

        $updated = Yii::$app->db->createCommand()->update('{{%auth_challenge}}', [
            'state' => 'approved',
            'approved_user_id' => (int) $mobileUser->id,
        ], [
            'id' => $challengeId,
            'purpose' => 'qr-login',
            'state' => 'pending',
        ])->execute();
        if ($updated !== 1) {
            throw new BadRequestHttpException('El código QR expiró o ya fue utilizado.');
        }

        Yii::$app->db->createCommand()->update('{{%auth_challenge}}', [
            'expires_at' => time() + 60,
        ], [
            'id' => $challengeId,
            'purpose' => 'qr-login',
            'state' => 'approved',
        ])->execute();
    }

    /**
     * @return array{status: string, userId?: int}
     */
    public static function status(string $webSessionId, string $challengeId): array
    {
        $challenge = (new Query())
            ->from('{{%auth_challenge}}')
            ->where([
                'id' => $challengeId,
                'purpose' => 'qr-login',
                'session_hash' => hash('sha256', $webSessionId),
            ])
            ->one();
        if ($challenge === false) {
            return ['status' => 'not_found'];
        }

        if ((int) $challenge['expires_at'] <= time() && $challenge['state'] === 'pending') {
            return ['status' => 'expired'];
        }

        if ($challenge['state'] === 'approved') {
            $userId = (int) $challenge['approved_user_id'];
            Yii::$app->db->createCommand()->update('{{%auth_challenge}}', [
                'state' => 'consumed',
                'consumed_at' => time(),
            ], ['id' => $challengeId, 'state' => 'approved'])->execute();

            return ['status' => 'approved', 'userId' => $userId];
        }

        return ['status' => $challenge['state']];
    }

    private static function qrPayload(string $challengeId): string
    {
        $baseUrl = trim((string) (Yii::$app->params['mobileAppDeepLinkBase'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = 'https://app.bioenlace.io';
        }

        return rtrim($baseUrl, '/') . '/qr-login/' . $challengeId;
    }
}
