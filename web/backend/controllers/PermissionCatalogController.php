<?php

namespace backend\controllers;

use common\components\Core\Permission\CatalogPermissionSyncService;
use common\components\Core\Permission\DataAccessGrantMigratorService;
use common\components\Core\Permission\PermissionCatalogService;
use common\components\Core\Permission\RolePermissionAssignmentService;
use common\components\Core\Permission\RolePermissionMatrixService;
use common\components\Core\Permission\Validation\CatalogIntegrityService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Catálogo de permisos declarativos (intents + atributos) e integridad.
 */
class PermissionCatalogController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'integrity' => ['GET', 'POST'],
                    'sync' => ['POST'],
                    'migrate-grants' => ['POST'],
                    'edit-role' => ['GET', 'POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $catalog = new PermissionCatalogService();

        return $this->render('index', [
            'intents' => $catalog->listIntents(),
            'attributes' => $catalog->listAttributes(),
            'flowSteps' => $catalog->listFlowStepDependencies(),
        ]);
    }

    public function actionIntegrity()
    {
        $result = (new CatalogIntegrityService())->run();

        return $this->render('integrity', [
            'result' => $result,
        ]);
    }

    public function actionRoles()
    {
        $matrix = (new RolePermissionMatrixService())->buildMatrix();
        $assignment = new RolePermissionAssignmentService();

        return $this->render('roles', [
            'matrix' => $matrix,
            'roleNames' => $assignment->listRoleNames(),
        ]);
    }

    public function actionSync()
    {
        $deactivate = (bool) Yii::$app->request->post('deactivate_legacy_grants', false);
        $result = (new CatalogPermissionSyncService())->syncAll(true, $deactivate);
        $catalog = $result['catalog'];
        $grants = $result['grants'];
        $msg = sprintf(
            'Catálogo: %d permiso(s) creado(s), %d enlace(s), %d grant(s) rol. Grants legacy: %d procesado(s), %d enlace(s) rol, %d permiso(s) creado(s).',
            $catalog['created'],
            $catalog['linked'],
            $catalog['role_grants'],
            $grants['grants_processed'],
            $grants['role_links_added'],
            $grants['permissions_created']
        );
        $errors = array_merge($catalog['errors'], $grants['errors']);
        if ($errors !== []) {
            Yii::$app->session->setFlash('error', $msg . ' Errores: ' . implode('; ', $errors));
        } else {
            Yii::$app->session->setFlash('success', $msg);
        }

        return $this->redirect(['roles']);
    }

    public function actionMigrateGrants()
    {
        $deactivate = (bool) Yii::$app->request->post('deactivate_legacy_grants', false);
        $grants = (new DataAccessGrantMigratorService())->migrate($deactivate);
        $msg = sprintf(
            'Migración grants: %d procesado(s), %d permiso(s) creado(s), %d enlace(s) rol.',
            $grants['grants_processed'],
            $grants['permissions_created'],
            $grants['role_links_added']
        );
        if ($grants['errors'] !== []) {
            Yii::$app->session->setFlash('error', $msg . ' Errores: ' . implode('; ', $grants['errors']));
        } else {
            Yii::$app->session->setFlash('success', $msg);
        }

        return $this->redirect(['roles']);
    }

    public function actionEditRole(string $role)
    {
        $assignment = new RolePermissionAssignmentService();
        $role = trim($role);
        if ($role === '' || !in_array($role, $assignment->listRoleNames(), true)) {
            throw new NotFoundHttpException('Rol no encontrado.');
        }

        $permissions = $assignment->catalogPermissionsForRole($role);

        if (Yii::$app->request->isPost) {
            $selected = Yii::$app->request->post('permissions', []);
            if (!is_array($selected)) {
                $selected = [];
            }
            try {
                $assignment->saveRolePermissions($role, $selected);
                Yii::$app->session->setFlash('success', 'Permisos actualizados para «' . $role . '».');
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }

            return $this->redirect(['edit-role', 'role' => $role]);
        }

        return $this->render('edit-role', [
            'roleName' => $role,
            'permissions' => $permissions,
        ]);
    }
}
