<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use common\models\DataAccess\DataAccessRoleGrant;

/**
 * CRUD admin de grants DataAccess por rol (tabla data_access_role_grant).
 */
class DataAccessGrantController extends Controller
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
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => DataAccessRoleGrant::find()->orderBy(['role_name' => SORT_ASC, 'entity_group_key' => SORT_ASC]),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'orphanRoleGrants' => DataAccessRoleGrant::orphanRoleGrantCounts(),
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        Yii::$app->session->setFlash(
            'warning',
            'Los grants por grupo legacy están en desuso. Asigná permisos atómicos en Catálogo de permisos → Roles.'
        );

        return $this->redirect(['/permission-catalog/roles']);
    }

    public function actionUpdate($id)
    {
        Yii::$app->session->setFlash(
            'warning',
            'Los grants por grupo legacy están en desuso. Asigná permisos atómicos en Catálogo de permisos → Roles.'
        );

        return $this->redirect(['/permission-catalog/roles']);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Grant eliminado.');

        return $this->redirect(['index']);
    }

    protected function findModel($id): DataAccessRoleGrant
    {
        $model = DataAccessRoleGrant::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Grant no encontrado.');
        }

        return $model;
    }
}
