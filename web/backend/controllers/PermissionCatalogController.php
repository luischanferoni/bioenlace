<?php

namespace backend\controllers;

use common\components\Core\Permission\CatalogPermissionSyncService;
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
                'class' => \frontend\components\BioenlaceBackendAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'integrity' => ['GET', 'POST'],
                    'sync' => ['POST'],
                    'edit-role' => ['GET', 'POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $catalog = new PermissionCatalogService();
        $matrix = (new RolePermissionMatrixService())->buildMatrix();
        $rolesByKey = [];
        $inAuthItemByKey = [];
        foreach ($matrix as $row) {
            $rolesByKey[$row['key']] = $row['roles'];
            $inAuthItemByKey[$row['key']] = (bool) $row['in_auth_item'];
        }

        $unregistered = array_filter(
            $matrix,
            static fn (array $r): bool => !$r['in_auth_item'] && strncmp($r['key'], '/api/', 5) !== 0
        );
        $unassigned = array_filter($matrix, static fn (array $r): bool => $r['roles'] === []);

        return $this->render('index', [
            'intents' => $catalog->listIntents(),
            'attributes' => $catalog->listAttributes(),
            'flowSteps' => $catalog->listFlowStepDependencies(),
            'rolesByKey' => $rolesByKey,
            'inAuthItemByKey' => $inAuthItemByKey,
            'roleNames' => (new RolePermissionAssignmentService())->listRoleNames(),
            'unregisteredCount' => count($unregistered),
            'unassignedCount' => count($unassigned),
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
        return $this->redirect(['index']);
    }

    public function actionSync()
    {
        $catalog = (new CatalogPermissionSyncService())->sync(true);
        $msg = sprintf(
            'Catálogo: %d permiso(s) creado(s), %d enlace(s), %d grant(s) rol.',
            $catalog['created'],
            $catalog['linked'],
            $catalog['role_grants']
        );
        if ($catalog['errors'] !== []) {
            Yii::$app->session->setFlash('error', $msg . ' Errores: ' . implode('; ', $catalog['errors']));
        } else {
            Yii::$app->session->setFlash('success', $msg);
        }

        return $this->redirect(['index']);
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
