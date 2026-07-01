<?php

namespace common\components\Platform\Core\Auth;

use common\models\User;
use common\models\UserAccountInvitationLog;
use Yii;
use yii\helpers\Url;

/**
 * Alta de cuentas staff por invitación (email o código presencial).
 */
final class StaffAccountInvitationService
{
    public const ACTIVATION_METHOD_EMAIL = 'email';

    public const ACTIVATION_METHOD_CODE = 'code';

    public static function invitationTokenExpireSeconds(): int
    {
        return (int) (Yii::$app->params['user.accountInvitationTokenExpire'] ?? 72 * 3600);
    }

    public static function activationCodeExpireSeconds(): int
    {
        return (int) (Yii::$app->params['user.accountActivationCodeExpire'] ?? 48 * 3600);
    }

    public static function activationCodeLength(): int
    {
        $len = (int) (Yii::$app->params['user.accountActivationCodeLength'] ?? 8);

        return max(6, min(10, $len));
    }

    public static function isPasswordSet(User $user): bool
    {
        return $user->password_set_at !== null && (int) $user->password_set_at > 0;
    }

    public static function isPendingActivation(User $user): bool
    {
        return (int) $user->status === User::STATUS_ACTIVE && !self::isPasswordSet($user);
    }

    /**
     * Usuario nuevo sin contraseña elegida: hash interno aleatorio.
     */
    public static function prepareInviteUser(User $user): void
    {
        $user->setPassword(Yii::$app->security->generateRandomString(64));
        $user->password_set_at = null;
        $user->email_confirmed = 0;
        $user->activation_code_hash = null;
        $user->activation_code_expires_at = null;
        $user->removeConfirmationToken();
    }

    public static function sendEmailInvitation(User $user, ?int $actorUserId, bool $isResend = false): bool
    {
        if (!self::isPendingActivation($user)) {
            throw new \InvalidArgumentException('La cuenta ya fue activada.');
        }

        $email = trim((string) $user->email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El usuario no tiene un e-mail válido.');
        }

        if (!Yii::$app->has('mailer')) {
            Yii::warning('StaffAccountInvitationService: mailer no configurado', 'auth.invitation');

            return false;
        }

        $user->generateConfirmationToken();
        if (!$user->save(false)) {
            return false;
        }

        $activateUrl = Url::to(['/auth/activate-account-receive', 'token' => $user->confirmation_token], true);
        $from = (string) (Yii::$app->params['senderEmail'] ?? Yii::$app->params['supportEmail'] ?? 'noreply@example.com');
        $fromName = (string) (Yii::$app->params['senderName'] ?? Yii::$app->name);
        $expireHours = (int) ceil(self::invitationTokenExpireSeconds() / 3600);

        $sent = Yii::$app->mailer->compose('@frontend/views/mail/accountInvitation', [
            'user' => $user,
            'activateUrl' => $activateUrl,
            'expireHours' => $expireHours,
        ])
            ->setFrom([$from => $fromName])
            ->setTo($email)
            ->setSubject('Activá tu acceso — ' . Yii::$app->name)
            ->send();

        if ($sent) {
            UserAccountInvitationLog::record(
                (int) $user->id,
                $isResend ? UserAccountInvitationLog::ACTION_EMAIL_RESENT : UserAccountInvitationLog::ACTION_EMAIL_SENT,
                $actorUserId,
                ['email' => $email]
            );
        }

        return $sent;
    }

    /**
     * Genera código numérico de un solo uso (se muestra una vez al admin).
     */
    public static function generateActivationCode(User $user, ?int $actorUserId): string
    {
        if (!self::isPendingActivation($user)) {
            throw new \InvalidArgumentException('La cuenta ya fue activada.');
        }

        $length = self::activationCodeLength();
        $max = (int) str_repeat('9', $length);
        $plain = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $user->activation_code_hash = Yii::$app->security->generatePasswordHash($plain);
        $user->activation_code_expires_at = time() + self::activationCodeExpireSeconds();
        if (!$user->save(false)) {
            throw new \RuntimeException('No se pudo guardar el código de activación.');
        }

        UserAccountInvitationLog::record(
            (int) $user->id,
            UserAccountInvitationLog::ACTION_CODE_GENERATED,
            $actorUserId,
            ['expires_at' => $user->activation_code_expires_at]
        );

        return $plain;
    }

    public static function validateActivationCode(User $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || $user->activation_code_hash === null || $user->activation_code_expires_at === null) {
            return false;
        }

        if ((int) $user->activation_code_expires_at < time()) {
            return false;
        }

        return Yii::$app->security->validatePassword($code, (string) $user->activation_code_hash);
    }

    /**
     * Establece contraseña elegida por el usuario y marca la cuenta como activada.
     */
    public static function activateWithPassword(User $user, string $password, string $method, ?int $actorUserId = null): bool
    {
        if (!self::isPendingActivation($user)) {
            throw new \InvalidArgumentException('La cuenta ya fue activada.');
        }

        $user->setPassword($password);
        $user->password_set_at = time();
        $user->email_confirmed = 1;
        $user->removeConfirmationToken();
        $user->activation_code_hash = null;
        $user->activation_code_expires_at = null;

        if (!$user->save(false)) {
            return false;
        }

        UserAccountInvitationLog::record(
            (int) $user->id,
            UserAccountInvitationLog::ACTION_ACTIVATED,
            $actorUserId,
            ['method' => $method]
        );

        return true;
    }

    public static function revokeInvitation(User $user, ?int $actorUserId): void
    {
        if (!self::isPendingActivation($user)) {
            throw new \InvalidArgumentException('La cuenta ya fue activada.');
        }

        $user->removeConfirmationToken();
        $user->activation_code_hash = null;
        $user->activation_code_expires_at = null;
        $user->save(false);

        UserAccountInvitationLog::record((int) $user->id, UserAccountInvitationLog::ACTION_REVOKED, $actorUserId);
    }

    public static function findByInvitationToken(string $token): ?User
    {
        return User::findByConfirmationToken($token, self::invitationTokenExpireSeconds());
    }

    public static function findPendingByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $user = User::findOne(['username' => $username, 'status' => User::STATUS_ACTIVE]);
        if ($user === null || self::isPasswordSet($user)) {
            return null;
        }

        return $user;
    }

    public static function recordCreated(User $user, ?int $actorUserId): void
    {
        UserAccountInvitationLog::record((int) $user->id, UserAccountInvitationLog::ACTION_CREATED, $actorUserId);
    }

    /**
     * @return bool true si guardó el usuario invitado
     */
    public static function saveInvitedUser(User $user, ?int $actorUserId): bool
    {
        self::prepareInviteUser($user);
        if (!$user->save()) {
            return false;
        }
        self::recordCreated($user, $actorUserId);

        return true;
    }
}
