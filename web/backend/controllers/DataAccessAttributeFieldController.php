<?php

namespace backend\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use common\models\DataAccess\DataAccessAttributeField;

/**
 * CRUD admin de campos editables por grupo (tabla data_access_attribute_field).
 */
class DataAccessAttributeFieldController extends Controller
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
        $groupFilter = trim((string) Yii::$app->request->get('group', ''));

        $query = DataAccessAttributeField::find()
            ->orderBy(['entity_group_key' => SORT_ASC, 'sort_order' => SORT_ASC, 'field_name' => SORT_ASC]);
        if ($groupFilter !== '') {
            $query->andWhere(['entity_group_key' => $groupFilter]);
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => $query,
                'pagination' => ['pageSize' => 50],
            ]),
            'groupFilter' => $groupFilter,
            'entityGroups' => (new \common\components\Core\DataAccess\AttributeGroupCatalog())->listEntityGroupOptions(),
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
        $model = new DataAccessAttributeField();
        $model->active = 1;
        $model->sort_order = 0;
        $groupFilter = trim((string) Yii::$app->request->get('group', ''));
        if ($groupFilter !== '') {
            $model->entity_group_key = $groupFilter;
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Campo creado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Campo actualizado.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $group = $model->entity_group_key;
        $model->delete();
        Yii::$app->session->setFlash('success', 'Campo eliminado.');

        return $this->redirect(['index', 'group' => $group]);
    }

    protected function findModel($id): DataAccessAttributeField
    {
        $model = DataAccessAttributeField::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Campo no encontrado.');
        }

        return $model;
    }
}
