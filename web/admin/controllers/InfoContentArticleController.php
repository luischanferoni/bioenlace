<?php

namespace admin\controllers;

use Yii;
use common\models\InfoContentArticle;
use common\models\busquedas\InfoContentArticleBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

class InfoContentArticleController extends Controller
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
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new InfoContentArticleBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new InfoContentArticle();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete(int $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): InfoContentArticle
    {
        $model = InfoContentArticle::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('El artículo no existe.');
        }

        return $model;
    }
}
