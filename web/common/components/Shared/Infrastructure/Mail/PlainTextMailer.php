<?php

namespace common\components\Shared\Infrastructure\Mail;

use Yii;

/**
 * Envío de un e-mail de texto plano.
 *
 * No lanza: devuelve el resultado para que el flujo que notifica decida qué hacer.
 * Concentra el acceso a `mailer` y el log de fallos en un solo lugar.
 */
final class PlainTextMailer
{
    /**
     * @return array{sent: bool, reason: string}
     */
    public function send(string $to, string $subject, string $body): array
    {
        $to = strtolower(trim($to));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'reason' => 'invalid_email'];
        }

        if (!Yii::$app->has('mailer')) {
            return ['sent' => false, 'reason' => 'mailer_unavailable'];
        }

        try {
            $sent = (bool) Yii::$app->mailer
                ->compose()
                ->setTo($to)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();

            return ['sent' => $sent, 'reason' => $sent ? '' : 'send_failed'];
        } catch (\Throwable $e) {
            Yii::error('PlainTextMailer: ' . $e->getMessage(), 'mail');

            return ['sent' => false, 'reason' => 'exception'];
        }
    }
}
