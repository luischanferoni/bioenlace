<?php

namespace common\models\forms;

use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\Url;

class PasswordRecoveryForm extends Model
{
    /** @var User|null */
    private $user;

    /** @var string */
    public $email;

    /** @var string */
    public $captcha;

    public function rules(): array
    {
        return [
            [['captcha'], 'captcha', 'captchaAction' => '/auth/captcha'],
            [['email', 'captcha'], 'required'],
            [['email'], 'trim'],
            [['email'], 'email'],
            [['email'], 'validateEmailConfirmedAndUserActive'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'E-mail',
            'captcha' => 'Captcha',
        ];
    }

    public function validateEmailConfirmedAndUserActive(): void
    {
        $user = User::findOne([
            'email' => $this->email,
            'email_confirmed' => 1,
            'status' => User::STATUS_ACTIVE,
        ]);

        if ($user === null) {
            $this->addError('email', 'E-mail no válido o no confirmado');

            return;
        }

        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function sendEmail(bool $performValidation = true): bool
    {
        if ($performValidation && !$this->validate()) {
            return false;
        }

        $user = $this->user;
        if ($user === null) {
            return false;
        }

        $user->generateConfirmationToken();
        if (!$user->save(false)) {
            return false;
        }

        if (!Yii::$app->has('mailer')) {
            Yii::warning('PasswordRecoveryForm: componente mailer no configurado', 'auth.password');

            return false;
        }

        $resetUrl = Url::to(['/auth/password-recovery-receive', 'token' => $user->confirmation_token], true);
        $from = (string) (Yii::$app->params['senderEmail'] ?? Yii::$app->params['supportEmail'] ?? 'noreply@example.com');
        $fromName = (string) (Yii::$app->params['senderName'] ?? Yii::$app->name);

        return Yii::$app->mailer->compose('@frontend/views/mail/passwordRecovery', [
            'user' => $user,
            'resetUrl' => $resetUrl,
        ])
            ->setFrom([$from => $fromName])
            ->setTo($this->email)
            ->setSubject('Restablecer contraseña — ' . Yii::$app->name)
            ->send();
    }
}
