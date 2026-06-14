<?php

namespace common\models\forms;

use common\models\User;
use Yii;
use yii\base\Model;

class ChangeOwnPasswordForm extends Model
{
    public const SCENARIO_RESTORE_VIA_EMAIL = 'restoreViaEmail';

    /** @var User */
    public $user;

    /** @var string */
    public $current_password;

    /** @var string */
    public $password;

    /** @var string */
    public $repeat_password;

    public function rules(): array
    {
        $minLength = (int) (Yii::$app->params['user.passwordMinLength'] ?? 6);
        if ($minLength < 6) {
            $minLength = 6;
        }

        return [
            [['password', 'repeat_password'], 'required'],
            [['password', 'repeat_password', 'current_password'], 'string', 'max' => 255],
            [['password', 'repeat_password', 'current_password'], 'trim'],
            [['password'], 'string', 'min' => $minLength],
            [['repeat_password'], 'compare', 'compareAttribute' => 'password'],
            [['current_password'], 'required', 'except' => self::SCENARIO_RESTORE_VIA_EMAIL],
            [['current_password'], 'validateCurrentPassword', 'except' => self::SCENARIO_RESTORE_VIA_EMAIL],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'current_password' => 'Contraseña actual',
            'password' => 'Contraseña nueva',
            'repeat_password' => 'Repetir contraseña',
        ];
    }

    public function validateCurrentPassword(): void
    {
        if (!$this->user->validatePassword($this->current_password)) {
            $this->addError('current_password', 'Contraseña incorrecta');
        }
    }

    public function changePassword(bool $performValidation = true): bool
    {
        if ($performValidation && !$this->validate()) {
            return false;
        }

        $this->user->password = $this->password;
        $this->user->scenario = 'changePassword';
        $this->user->removeConfirmationToken();

        return $this->user->save(false);
    }
}
