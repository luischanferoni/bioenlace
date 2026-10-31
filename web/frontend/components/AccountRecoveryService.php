<?php

namespace frontend\components;

use common\components\Platform\Core\Auth\ApiAuthSessionService;
use common\models\Platform\User;
use Yii;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Recuperación asistida de acceso para cuentas web-only.
 *
 * Un usuario sin passkey ni acceso solicita recuperación. Un operador autorizado la
 * revisa y aprueba, lo que genera un código de un solo uso y revoca sesiones y passkeys
 * anteriores. El usuario consume el código y debe enrolar una passkey nueva.
 */
final class AccountRecoveryService
{
    private const CODE_TTL_SECONDS = 900;

    public static function request(string $identifier, string $note): void
    {
        $identifier = trim($identifier);
        $user = self::resolveUserByIdentifier($identifier);
        $requestId = bin2hex(random_bytes(32));
        $now = time();

        Yii::$app->db->createCommand()->insert('{{%account_recovery_request}}', [
            'id' => $requestId,
            'id_user' => $user !== null ? (int) $user->id : 0,
            'status' => 'pending',
            'request_ip_hash' => hash('sha256', (string) (Yii::$app->request->userIP ?? '')),
            'request_note' => mb_substr(trim($note), 0, 500),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'recovery_code_hash' => null,
            'recovery_code_expires_at' => null,
            'recovery_code_used_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        Yii::info(
            'account-recovery request id=' . $requestId . ' identifier=' . hash('sha256', $identifier) . ' matched_user=' . ($user !== null ? 'yes' : 'no'),
            'account-recovery'
        );
    }

    public static function approve(string $requestId, User $reviewer): string
    {
        $request = (new Query())
            ->from('{{%account_recovery_request}}')
            ->where(['id' => $requestId, 'status' => 'pending'])
            ->one();
        if ($request === false) {
            throw new NotFoundHttpException('La solicitud de recuperación no existe o ya fue revisada.');
        }

        $userId = (int) $request['id_user'];
        if ($userId <= 0) {
            throw new BadRequestHttpException('La solicitud no tiene una cuenta asociada; verificar la identidad antes de aprobar.');
        }

        $user = User::findIdentity($userId);
        if (!$user instanceof User || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new BadRequestHttpException('La cuenta asociada no está habilitada.');
        }

        $code = self::generateCode();
        $codeHash = hash('sha256', $code);

        // Revocar todo acceso previo antes de emitir la recuperación.
        ApiAuthSessionService::revokeAllForUser($userId);
        Yii::$app->db->createCommand()->update('{{%webauthn_credential}}', [
            'revoked_at' => time(),
        ], ['id_user' => $userId, 'revoked_at' => null])->execute();

        Yii::$app->db->createCommand()->update('{{%account_recovery_request}}', [
            'status' => 'approved',
            'reviewed_by' => (int) $reviewer->id,
            'reviewed_at' => time(),
            'recovery_code_hash' => $codeHash,
            'recovery_code_expires_at' => time() + self::CODE_TTL_SECONDS,
            'updated_at' => time(),
        ], ['id' => $requestId, 'status' => 'pending'])->execute();

        Yii::info('account-recovery approved id=' . $requestId . ' user=' . $userId, 'account-recovery');

        return $code;
    }

    public static function consume(string $code): User
    {
        $code = trim($code);
        if ($code === '') {
            throw new BadRequestHttpException('El código de recuperación es requerido.');
        }

        $request = (new Query())
            ->from('{{%account_recovery_request}}')
            ->where([
                'recovery_code_hash' => hash('sha256', $code),
                'status' => 'approved',
                'recovery_code_used_at' => null,
            ])
            ->andWhere(['>', 'recovery_code_expires_at', time()])
            ->one();
        if ($request === false) {
            throw new BadRequestHttpException('El código de recuperación no es válido o ya fue utilizado.');
        }

        $user = User::findIdentity((int) $request['id_user']);
        if (!$user instanceof User || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new BadRequestHttpException('La cuenta asociada no está habilitada.');
        }

        Yii::$app->db->createCommand()->update('{{%account_recovery_request}}', [
            'recovery_code_used_at' => time(),
            'updated_at' => time(),
        ], ['id' => (string) $request['id'], 'recovery_code_used_at' => null])->execute();

        return $user;
    }

    private static function resolveUserByIdentifier(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        $user = User::find()
            ->where(['status' => User::STATUS_ACTIVE])
            ->andWhere(['or', ['username' => $identifier], ['email' => $identifier]])
            ->one();

        return $user instanceof User ? $user : null;
    }

    private static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return implode('', array_map(
            static fn () => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, 10)
        ));
    }
}
