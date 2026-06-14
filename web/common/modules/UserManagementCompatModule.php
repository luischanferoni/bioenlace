<?php

namespace common\modules;

use yii\base\Module;

/**
 * Módulo compatibilidad que reemplaza webvimark {@see \webvimark\modules\UserManagement\UserManagementModule}
 * en configuración Yii. Mantiene propiedades leídas por formularios y RBAC legacy.
 */
class UserManagementCompatModule extends Module
{
    public $commonPermissionName = 'commonPermission';

    public $userCanHaveMultipleRoles = true;

    public $confirmationTokenExpire = 3600;

    public $enableRegistration = false;

    public $captchaOptions = [
        'class' => 'yii\captcha\CaptchaAction',
        'minLength' => 3,
        'maxLength' => 4,
        'offset' => 5,
    ];

    public $user_table = '{{%user}}';

    public $user_visit_log_table = '{{%user_visit_log}}';

    public $auth_item_table = '{{%auth_item}}';

    public $auth_item_child_table = '{{%auth_item_child}}';

    public $auth_item_group_table = '{{%auth_item_group}}';

    public $auth_assignment_table = '{{%auth_assignment}}';

    public $auth_rule_table = '{{%auth_rule}}';

    public $controllerNamespace = 'backend\controllers';
}
