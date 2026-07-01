<?php

namespace common\models\forms;

use common\components\Platform\Core\Auth\StaffAccountInvitationService;
use common\models\User;
use Yii;
use yii\base\Model;

/**
 * Activación de cuenta staff con código presencial.
 */
class ActivateAccountForm extends Model
{
    /** @var string */
    public $username;

    /** @var string */
    public $activation_code;

    /** @var string */
    public $password;

    /** @var string */
    public $repeat_password;

    /** @var string */
    public $captcha;

    /** @var User|null */
    private $user;

    public function rules(): array
    {
        $minLength = (int) (Yii::$app->params['user.passwordMinLength'] ?? 8);
        if ($minLength < 6) {
            $minLength = 6;
        }

        return [
            [['username', 'activation_code', 'password', 'repeat_password', 'captcha'], 'required'],
            [['username', 'activation_code', 'password', 'repeat_password'], 'trim'],
            [['username'], 'string', 'max' => 255],
            [['activation_code'], 'string', 'min' => 6, 'max' => 10],
            [['password'], 'string', 'min' => $minLength, 'max' => 255],
            [['repeat_password'], 'compare', 'compareAttribute' => 'password'],
            [['captcha'], 'captcha', 'captchaAction' => '/auth/captcha'],
            [['activation_code'], 'validateActivationCode'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Usuario',
            'activation_code' => 'Código de activación',
            'password' => 'Contraseña nueva',
            'repeat_password' => 'Repetir contraseña',
            'captcha' => 'Captcha',
        ];
    }

    public function validateActivationCode(): void
    {
        $user = StaffAccountInvitationService::findPendingByUsername((string) $this->username);
        if ($user === null) {
            $this->addError('username', 'Usuario no encontrado o cuenta ya activada.');

            return;
        }

        if (!StaffAccountInvitationService::validateActivationCode($user, (string) $this->activation_code)) {
            $this->addError('activation_code', 'Código inválido o expirado.');

            return;
        }

        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function activate(bool $performValidation = true): bool
    {
        if ($performValidation && !$this->validate()) {
            return false;
        }

        $user = $this->user;
        if ($user === null) {
            return false;
        }

        return StaffAccountInvitationService::activateWithPassword(
            $user,
            (string) $this->password,
            StaffAccountInvitationService::ACTIVATION_METHOD_CODE
        );
    }
}
