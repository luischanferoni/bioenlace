<?php

namespace backend\controllers;

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\PermissionCatalogService;
use common\components\Platform\Core\Permission\PermissionRolesAssignmentService;
use common\components\Platform\Core\Permission\RolePermissionAssignmentService;
use common\components\Platform\Core\Permission\RolePermissionMatrixService;
use common\components\Platform\Core\Permission\Validation\CatalogIntegrityService;
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
                    'edit-attribute-roles' => ['GET', 'POST'],
                    'edit-intent-roles' => ['GET', 'POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $catalog = new PermissionCatalogService();
        $matrix = new RolePermissionMatrixService();
        $attributesByEntity = $catalog->listAttributesGroupedByEntity();
        $rolesByKey = [];
        $inAuthItemByKey = [];
        $assignment = new RolePermissionAssignmentService();

        foreach ($catalog->listAttributes() as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rolesByKey[$key] = $matrix->buildMatrixRowRoles($key);
            $inAuthItemByKey[$key] = $assignment->permissionExistsInAuthItem($key);
        }

        $intentInAuth = [];
        foreach ($catalog->listIntents() as $intent) {
            $key = trim((string) ($intent['key'] ?? ''));
            if ($key !== '' && strncmp($key, '/api/', 5) !== 0) {
                $intentInAuth[$key] = $assignment->permissionExistsInAuthItem($key);
                $rolesByKey[$key] = $matrix->buildMatrixRowRoles($key);
            }
        }

        $unregisteredAttributes = count(array_filter(
            $inAuthItemByKey,
            static fn (bool $ok): bool => !$ok
        ));
        $unregisteredIntents = count(array_filter(
            $intentInAuth,
            static fn (bool $ok): bool => !$ok
        ));

        return $this->render('index', [
            'intents' => $catalog->listIntents(),
            'attributesByEntity' => $attributesByEntity,
            'flowSteps' => $catalog->listFlowStepDependencies(),
            'rolesByKey' => $rolesByKey,
            'inAuthItemByKey' => $inAuthItemByKey,
            'intentInAuth' => $intentInAuth,
            'roleNames' => $assignment->listRoleNames(),
            'unregisteredAttributesCount' => $unregisteredAttributes,
            'unregisteredIntentsCount' => $unregisteredIntents,
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
        return $this->redirect(['/user-management/role/index']);
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

    /** @deprecated Redirige al CRUD de roles (intents por rol). */
    public function actionEditRole(string $role)
    {
        return $this->redirect(['/user-management/role/update', 'name' => $role]);
    }

    public function actionEditIntentRoles(string $key)
    {
        return $this->editPermissionRoles($key, 'tab-intents');
    }

    public function actionEditAttributeRoles(string $key)
    {
        return $this->editPermissionRoles($key, 'tab-attributes');
    }

    /**
     * @return string|\yii\web\Response
     */
    private function editPermissionRoles(string $key, string $returnTab)
    {
        $key = trim($key);
        $catalogRow = (new PermissionCatalogService())->findPermissionRow($key);
        if ($catalogRow === null) {
            throw new NotFoundHttpException('Permiso no encontrado en el catálogo.');
        }

        $service = new PermissionRolesAssignmentService();
        $assignment = new RolePermissionAssignmentService();
        $roleNames = $assignment->listRoleNames();
        $assignedRoles = array_flip($service->rolesWithPermission($key));
        $kind = (string) ($catalogRow['kind'] ?? '');

        if (Yii::$app->request->isPost) {
            $selected = Yii::$app->request->post('roles', []);
            if (!is_array($selected)) {
                $selected = [];
            }
            try {
                $service->saveRolesForPermission($key, $selected);
                Yii::$app->session->setFlash('success', 'Roles actualizados para «' . $key . '».');
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }

            $redirectAction = $kind === 'intent' ? 'edit-intent-roles' : 'edit-attribute-roles';

            return $this->redirect([$redirectAction, 'key' => $key]);
        }

        return $this->render('edit-permission-roles', [
            'permissionKey' => $key,
            'catalogRow' => $catalogRow,
            'roleNames' => $roleNames,
            'assignedRoles' => $assignedRoles,
            'inAuthItem' => $assignment->permissionExistsInAuthItem($key),
            'returnTab' => $returnTab,
        ]);
    }
}
