<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Person\Persona;
use common\models\Persona_mails;
use common\models\PersonaTelefono;
use common\models\User;
use Yii;

/**
 * Envío stub de email/SMS para escalada multicanal (v1: log + mailer opcional).
 */
final class TurnoOutboundChannelStub
{
    public function sendEmail(int $idPersona, string $subject, string $body): bool
    {
        $to = $this->resolveEmail($idPersona);
        $payload = [
            'channel' => 'email',
            'persona_id' => $idPersona,
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];
        Yii::info($payload, 'turno-multicanal-stub');

        if ($to === null || $to === '') {
            return false;
        }

        try {
            if (Yii::$app->has('mailer')) {
                return (bool) Yii::$app->mailer->compose()
                    ->setTo($to)
                    ->setSubject($subject)
                    ->setTextBody($body)
                    ->send();
            }
        } catch (\Throwable $e) {
            Yii::warning('TurnoOutboundChannelStub email: ' . $e->getMessage(), 'turno-multicanal-stub');
        }

        return false;
    }

    public function sendSms(int $idPersona, string $body): bool
    {
        $to = $this->resolveSmsNumber($idPersona);
        $payload = [
            'channel' => 'sms',
            'persona_id' => $idPersona,
            'to' => $to,
            'body' => $body,
        ];
        Yii::info($payload, 'turno-multicanal-stub');

        return $to !== null && $to !== '';
    }

    private function resolveEmail(int $idPersona): ?string
    {
        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            return null;
        }

        if ((int) ($persona->id_user ?? 0) > 0) {
            $user = User::findOne((int) $persona->id_user);
            if ($user !== null && filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                return (string) $user->email;
            }
        }

        /** @var Persona_mails|null $mail */
        $mail = Persona_mails::find()
            ->where(['id_persona' => $idPersona])
            ->orderBy(['id_persona_mail' => SORT_DESC])
            ->one();
        if ($mail !== null && filter_var((string) $mail->mail, FILTER_VALIDATE_EMAIL)) {
            return (string) $mail->mail;
        }

        return null;
    }

    private function resolveSmsNumber(int $idPersona): ?string
    {
        /** @var PersonaTelefono|null $tel */
        $tel = PersonaTelefono::find()
            ->where(['id_persona' => $idPersona])
            ->orderBy(['id_persona_telefono' => SORT_DESC])
            ->one();
        if ($tel === null) {
            return null;
        }

        $num = preg_replace('/\D+/', '', (string) $tel->numero);

        return $num !== '' ? $num : null;
    }
}
