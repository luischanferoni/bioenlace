<?php

namespace common\components\Platform\Core\Auth;

use common\components\Domain\Person\Identity\Application\Catalog\AccountRecoveryNotificationCatalog;
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

    public static function pendingRequests(): array
    {
        return (new Query())
            ->select(['r.*', 'u.username', 'u.email'])
            ->from(['r' => '{{%account_recovery_request}}'])
            ->leftJoin(['u' => '{{%user}}'], 'u.id = r.id_user')
            ->where(['r.status' => 'pending'])
            ->orderBy(['r.created_at' => SORT_ASC])
            ->all();
    }

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

        // Notificar al usuario que su solicitud fue aprobada
        self::notifyUser($user, 'approved');

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

        // Notificar al usuario que la recuperación fue completada
        self::notifyUser($user, 'consumed');

        return $user;
    }

    /**
     * Rechaza una solicitud de recuperación.
     *
     * @param string $requestId ID de la solicitud
     * @param User $reviewer Usuario que rechaza (superadmin)
     * @param string $reason Motivo del rechazo
     */
    public static function reject(string $requestId, User $reviewer, string $reason = ''): void
    {
        $request = (new Query())
            ->from('{{%account_recovery_request}}')
            ->where(['id' => $requestId, 'status' => 'pending'])
            ->one();
        if ($request === false) {
            throw new NotFoundHttpException('La solicitud de recuperación no existe o ya fue revisada.');
        }

        $userId = (int) $request['id_user'];
        $user = $userId > 0 ? User::findIdentity($userId) : null;

        Yii::$app->db->createCommand()->update('{{%account_recovery_request}}', [
            'status' => 'rejected',
            'reviewed_by' => (int) $reviewer->id,
            'reviewed_at' => time(),
            'request_note' => mb_substr(trim($reason), 0, 500),
            'updated_at' => time(),
        ], ['id' => $requestId, 'status' => 'pending'])->execute();

        Yii::info('account-recovery rejected id=' . $requestId . ' user=' . $userId . ' reason=' . $reason, 'account-recovery');

        // Notificar al usuario si existe una cuenta asociada
        if ($user instanceof User) {
            self::notifyUser($user, 'rejected');
        }
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

    /**
     * Notifica al usuario sobre el estado de su recuperación.
     *
     * NOTA: Esta es una implementación básica. Para producción:
     * - Configurar canal de notificación (email, push, in-app)
     * - Implementar cola de notificaciones para no bloquear el request
     * - Agregar retry y manejo de fallos
     *
     * @param User $user Usuario a notificar
     * @param string $event Tipo de evento (approved, consumed, rejected, fraud_alert)
     */
    private static function notifyUser(User $user, string $event): void
    {
        try {
            $catalog = new AccountRecoveryNotificationCatalog();
            $message = match ($event) {
                'reviewed' => $catalog->reviewed(),
                'approved' => $catalog->approved(),
                'consumed' => $catalog->consumed(),
                'rejected' => $catalog->rejected(),
                'fraud_alert' => $catalog->fraudAlert(),
                default => throw new \InvalidArgumentException("Evento de notificación desconocido: $event"),
            };

            // TODO: Implementar envío real de notificación
            // Por ahora, solo logueamos el mensaje
            Yii::info(
                'account-recovery notification user=' . $user->id .
                ' event=' . $event .
                ' title=' . $message['title'] .
                ' body=' . $message['body'],
                'account-recovery'
            );

            // Ejemplo de implementación futura:
            /*
            if ($user->email) {
                Yii::$app->mailer->compose()
                    ->setTo($user->email)
                    ->setSubject($message['title'])
                    ->setTextBody($message['body'])
                    ->send();
            }
            */
        } catch (\Throwable $e) {
            Yii::error(
                'Error enviando notificación de recuperación: ' . $e->getMessage(),
                'account-recovery'
            );
            // No fallar el flujo por error de notificación
        }
    }

    /**
     * Verifica señales de fraude y pone la cuenta en hold si es necesario.
     *
     * Señales consideradas:
     * - Múltiples solicitudes de recuperación en poco tiempo
     * - Solicitudes desde IPs diferentes de manera sospechosa
     * - Solicitudes con datos inconsistentes
     *
     * @param string $identifier Identificador de la solicitud
     * @param string $ip IP de la solicitud
     * @return bool True si se detectó fraude y la cuenta está en hold
     */
    public static function checkFraudSignals(string $identifier, string $ip): bool
    {
        // TODO: Implementar lógica de detección de fraude
        // Por ahora, retornamos false (sin fraude detectado)

        /*
        // Ejemplo de implementación futura:
        $user = self::resolveUserByIdentifier($identifier);
        if (!$user instanceof User) {
            return false;
        }

        $userId = (int) $user->id;
        $ipHash = hash('sha256', $ip);
        $recentWindow = time() - 3600; // Última hora

        // Contar solicitudes recientes del mismo usuario
        $recentRequests = (new Query())
            ->from('{{%account_recovery_request}}')
            ->where(['id_user' => $userId])
            ->andWhere(['>=', 'created_at', $recentWindow])
            ->count();

        if ((int) $recentRequests >= 3) {
            // Múltiples solicitudes en una hora - posible fraude
            self::putAccountOnHold($userId, 'multiple_recovery_requests');
            self::notifyUser($user, 'fraud_alert');
            return true;
        }

        // Contar solicitudes desde la misma IP
        $ipRequests = (new Query())
            ->from('{{%account_recovery_request}}')
            ->where(['request_ip_hash' => $ipHash])
            ->andWhere(['>=', 'created_at', $recentWindow])
            ->count();

        if ((int) $ipRequests >= 5) {
            // Múltiples solicitudes desde la misma IP - posible ataque
            self::putAccountOnHold($userId, 'high_frequency_from_ip');
            self::notifyUser($user, 'fraud_alert');
            return true;
        }
        */

        return false;
    }

    /**
     * Pone una cuenta en hold por sospecha de fraude.
     *
     * @param int $userId ID del usuario
     * @param string $reason Motivo del hold
     */
    private static function putAccountOnHold(int $userId, string $reason): void
    {
        // TODO: Implementar tabla de holds o flag en user
        // Por ahora, solo logueamos
        Yii::warning(
            'account-recovery putting user on hold user=' . $userId . ' reason=' . $reason,
            'account-recovery'
        );

        /*
        // Ejemplo de implementación futura:
        Yii::$app->db->createCommand()->update('{{%user}}', [
            'status' => User::STATUS_FRAUD_HOLD,
            'fraud_hold_reason' => $reason,
            'fraud_hold_at' => time(),
        ], ['id' => $userId])->execute();
        */
    }
}
