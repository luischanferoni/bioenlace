<?php

namespace backend\controllers;

use common\components\Core\Permission\PermissionCatalogService;
use common\components\Core\Permission\RolePermissionMatrixService;
use common\components\Core\Permission\Validation\CatalogIntegrityService;
use yii\filters\VerbFilter;
use yii\web\Controller;

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

        return $this->render('roles', [
            'matrix' => $matrix,
            'roleNames' => (new RolePermissionMatrixService())->listRoleNames(),
        ]);
    }
}
