<?php

namespace admin\controllers;

use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\PermissionCatalogService;
use common\components\Platform\Core\Permission\PermissionRolesAssignmentService;
use common\components\Platform\Core\Permission\RolePermissionAssignmentService;
use common\components\Platform\Core\Permission\Validation\CatalogIntegrityService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Catálogo de permisos declarativos (intents) e integridad.
 */
class PermissionCatalogController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'integrity' => ['GET', 'POST'],
                    'sync' => ['POST'],
                    'edit-intent-roles' => ['GET', 'POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $catalog = new PermissionCatalogService();
        $rolesByKey = [];
        $intentInAuth = [];
        $assignment = new RolePermissionAssignmentService();
        $matrix = new \common\components\Platform\Core\Permission\RolePermissionMatrixService();

        foreach ($catalog->listIntents() as $intent) {
            $key = trim((string) ($intent['key'] ?? ''));
            if ($key === '' || strncmp($key, '/api/', 5) === 0) {
                continue;
            }
            $intentInAuth[$key] = $assignment->permissionExistsInAuthItem($key);
            $rolesByKey[$key] = $matrix->buildMatrixRowRoles($key);
        }

        $unregisteredIntents = count(array_filter(
            $intentInAuth,
            static fn (bool $ok): bool => !$ok
        ));

        return $this->render('index', [
            'intents' => $catalog->listIntents(),
            'flowSteps' => $catalog->listFlowStepDependencies(),
            'rolesByKey' => $rolesByKey,
            'intentInAuth' => $intentInAuth,
            'roleNames' => $assignment->listRoleNames(),
            'unregisteredIntentsCount' => $unregisteredIntents,
        ]);
    }

    public function actionViewIntent(string $intent_id)
    {
        $manifest = (new PermissionCatalogService())->buildIntentFieldManifest($intent_id);
        if ($manifest === null) {
            throw new NotFoundHttpException('Intent no encontrado.');
        }

        $key = trim((string) ($manifest['key'] ?? ''));
        $assignment = new RolePermissionAssignmentService();
        $matrix = new \common\components\Platform\Core\Permission\RolePermissionMatrixService();

        return $this->render('view-intent', [
            'manifest' => $manifest,
            'roles' => $key !== '' ? $matrix->buildMatrixRowRoles($key) : [],
            'inAuthItem' => $key !== '' && $assignment->permissionExistsInAuthItem($key),
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
            'Catálogo: %d intent(s) creado(s), %d enlace(s), %d grant(s) rol.',
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
        return $this->editPermissionRoles($key);
    }

    /** @deprecated Permisos por atributo retirados del admin. */
    public function actionEditAttributeRoles(string $key)
    {
        Yii::$app->session->setFlash(
            'info',
            'Los permisos por atributo ya no se asignan desde el admin. Usá intents del catálogo.'
        );

        return $this->redirect(['index']);
    }

    /**
     * @return string|\yii\web\Response
     */
    private function editPermissionRoles(string $key)
    {
        $key = trim($key);
        $catalog = new PermissionCatalogService();
        $catalogRow = $catalog->findPermissionRow($key);
        if ($catalogRow === null) {
            throw new NotFoundHttpException('Intent no encontrado en el catálogo.');
        }

        $intentId = trim((string) ($catalogRow['intent_id'] ?? ''));
        $fieldManifest = $intentId !== '' ? $catalog->buildIntentFieldManifest($intentId) : null;

        $service = new PermissionRolesAssignmentService();
        $assignment = new RolePermissionAssignmentService();
        $roleNames = $assignment->listRoleNames();
        $assignedRoles = array_flip($service->rolesWithPermission($key));

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

            return $this->redirect(['edit-intent-roles', 'key' => $key]);
        }

        return $this->render('edit-permission-roles', [
            'permissionKey' => $key,
            'catalogRow' => $catalogRow,
            'fieldManifest' => $fieldManifest,
            'roleNames' => $roleNames,
            'assignedRoles' => $assignedRoles,
            'inAuthItem' => $assignment->permissionExistsInAuthItem($key),
        ]);
    }
}
