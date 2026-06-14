<?php

namespace common\models\forms;

use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\Url;

class ConfirmEmailForm extends Model
{
    /** @var User */
    public $user;

    /** @var string */
    public $email;

    public function init()
    {
        parent::init();
        if ($this->user !== null
            && $this->user->confirmation_token !== null
            && $this->getTokenTimeLeft() === 0
        ) {
            $this->user->removeConfirmationToken();
            $this->user->save(false);
        }
    }

    public function rules(): array
    {
        return [
            [['email'], 'required'],
            [['email'], 'trim'],
            [['email'], 'email'],
            [['email'], 'validateEmailConfirmedUnique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'E-mail',
        ];
    }

    public function validateEmailConfirmedUnique(): void
    {
        if (!$this->email) {
            return;
        }

        $exists = User::findOne([
            'email' => $this->email,
            'email_confirmed' => 1,
        ]);

        if ($exists !== null && (int) $exists->id !== (int) $this->user->id) {
            $this->addError('email', 'Este e-mail ya está registrado y confirmado');
        }
    }

    public function getTokenTimeLeft(bool $inMinutes = false): int
    {
        if ($this->user === null || !$this->user->confirmation_token) {
            return 0;
        }

        $expire = (int) (Yii::$app->params['user.passwordResetTokenExpire'] ?? 3600);
        $parts = explode('_', (string) $this->user->confirmation_token);
        $timestamp = (int) end($parts);
        $timeLeft = $timestamp + $expire - time();
        if ($timeLeft < 0) {
            return 0;
        }

        return $inMinutes ? (int) round($timeLeft / 60) : $timeLeft;
    }

    public function sendEmail(bool $performValidation = true): bool
    {
        if ($performValidation && !$this->validate()) {
            return false;
        }

        $this->user->email = $this->email;
        $this->user->email_confirmed = 0;
        $this->user->generateConfirmationToken();
        if (!$this->user->save(false)) {
            return false;
        }

        if (!Yii::$app->has('mailer')) {
            Yii::warning('ConfirmEmailForm: componente mailer no configurado', 'auth.email');

            return false;
        }

        $confirmUrl = Url::to(['/auth/confirm-email-receive', 'token' => $this->user->confirmation_token], true);
        $from = (string) (Yii::$app->params['senderEmail'] ?? Yii::$app->params['supportEmail'] ?? 'noreply@example.com');
        $fromName = (string) (Yii::$app->params['senderName'] ?? Yii::$app->name);

        return Yii::$app->mailer->compose('@frontend/views/mail/confirmEmail', [
            'user' => $this->user,
            'confirmUrl' => $confirmUrl,
        ])
            ->setFrom([$from => $fromName])
            ->setTo($this->email)
            ->setSubject('Confirmación de e-mail — ' . Yii::$app->name)
            ->send();
    }
}
