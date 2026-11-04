<?php

namespace common\components\Platform\Core\Auth;

use common\models\Platform\ConfirmEmailForm;
use common\models\Platform\User;
use Yii;

/**
 * Envío del mail de confirmación de e-mail para el alta comercial.
 *
 * Reusa [ConfirmEmailForm] (token + vista `mail/confirmEmail`) y agrega un
 * cooldown de reenvío para no golpear el SMTP ni permitir abuso.
 */
final class EmailConfirmationSender
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * @return array{email: string, email_confirmed: bool, sent: bool, cooldown_seconds: int}
     */
    public static function send(User $user, bool $force = false): array
    {
        $email = strtolower(trim((string) $user->email));
        $result = [
            'email' => $email,
            'email_confirmed' => (int) $user->email_confirmed === 1,
            'sent' => false,
            'cooldown_seconds' => 0,
        ];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                'La cuenta no tiene un e-mail válido para confirmar.'
            );
        }
        if ($result['email_confirmed']) {
            return $result;
        }
        if (!Yii::$app->has('mailer')) {
            throw new EmailDeliveryUnavailableException(
                'El envío de e-mails no está configurado.'
            );
        }

        if (!$force) {
            $remaining = self::secondsUntilResendAllowed($user);
            if ($remaining > 0) {
                $result['cooldown_seconds'] = $remaining;

                return $result;
            }
        }

        $form = new ConfirmEmailForm(['user' => $user]);
        $form->email = $email;
        $result['sent'] = $form->sendEmail(false);

        return $result;
    }

    /**
     * @return array{email: string, email_confirmed: bool}
     */
    public static function status(User $user): array
    {
        return [
            'email' => strtolower(trim((string) $user->email)),
            'email_confirmed' => (int) $user->email_confirmed === 1,
        ];
    }

    public static function secondsUntilResendAllowed(User $user): int
    {
        if (!$user->hasAttribute('confirmation_sent_at') || !$user->confirmation_sent_at) {
            return 0;
        }
        $sentAt = strtotime((string) $user->confirmation_sent_at);
        if ($sentAt === false) {
            return 0;
        }

        return max(0, $sentAt + self::RESEND_COOLDOWN_SECONDS - time());
    }
}
