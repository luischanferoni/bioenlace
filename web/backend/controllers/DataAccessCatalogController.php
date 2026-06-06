<?php

namespace backend\controllers;

use yii\web\Controller;
use yii\filters\VerbFilter;
use common\components\Core\DataAccess\AttributeGroupCatalog;

/**
 * Vista read-only del catálogo DataAccess (YAML de referencia).
 */
class DataAccessCatalogController extends Controller
{
    public function behaviors(): array
    {
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::class,
            ],
        ];
    }

    public function actionIndex()
    {
        $catalog = new AttributeGroupCatalog();

        return $this->render('index', [
            'entityGroups' => $catalog->listEntityGroupOptions(),
            'entities' => $catalog->listEntitiesForDisplay(),
            'metrics' => $catalog->listMetricsForDisplay(),
            'yamlRoleGrants' => $catalog->listYamlRoleGrants(),
        ]);
    }
}
