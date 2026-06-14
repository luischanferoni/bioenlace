<?php

namespace backend\controllers;

use common\models\User;
use common\models\webvimark\moduleusermanagement\models\rbacDB\SisseRole;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Asignación de roles a usuario (auth_assignment). Permisos por rol: {@see PermissionCatalogController}.
 */
class UserRoleController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceBackendAccessControl::class,
            ],
        ];
    }

    public function actionSet($id)
    {
        $user = User::findOne((int) $id);
        if ($user === null) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        return $this->render('@backend/views/user-management/user-permission/set', [
            'user' => $user,
        ]);
    }

    public function actionSetRoles($id)
    {
        $userId = (int) $id;
        if (!Yii::$app->user->isSuperadmin && (int) Yii::$app->user->id === $userId) {
            Yii::$app->session->setFlash('error', 'No puede modificar sus propios roles.');

            return $this->redirect(['set', 'id' => $userId]);
        }

        $oldAssignments = array_keys(Role::getUserRoles($userId));
        $available = array_map(
            static fn ($role) => $role->name,
            SisseRole::getAvailableRoles(true)
        );

        $posted = Yii::$app->request->post('roles', []);
        if (!is_array($posted)) {
            $posted = $posted !== null && $posted !== '' ? [$posted] : [];
        }

        $newAssignments = array_values(array_intersect($available, $posted));
        $toAssign = array_diff($newAssignments, $oldAssignments);
        $toRevoke = array_diff($oldAssignments, $newAssignments);

        foreach ($toRevoke as $role) {
            User::revokeRole($userId, $role);
        }
        foreach ($toAssign as $role) {
            User::assignRole($userId, $role);
        }

        Yii::$app->session->setFlash('success', 'Roles guardados.');

        return $this->redirect(['set', 'id' => $userId]);
    }
}
