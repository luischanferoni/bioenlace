<?php

namespace frontend\modules\api\v1\components;

use Yii;
use yii\filters\auth\AuthMethod;
use webvimark\modules\UserManagement\models\User as UserModel;

/**
 * Autenticación por sesión web (misma cookie que el frontend) cuando no hay Bearer.
 * Permite que el navegador llame a endpoints API sin JWT.
 */
class SessionIdentityAuth extends AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        if (!$user->getIsGuest()) {
            return $user->getIdentity();
        }
        $session = Yii::$app->session;
        if (!$session->isActive) {
            $session->open();
        }
        $id = $session->get($user->idParam ?? '__id');
        if (!$id) {
            return null;
        }
        $identity = UserModel::findIdentity($id);
        if (!$identity || (int) $identity->status !== UserModel::STATUS_ACTIVE) {
            return null;
        }
        $user->setIdentity($identity);
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions($user);

        return $identity;
    }
}
