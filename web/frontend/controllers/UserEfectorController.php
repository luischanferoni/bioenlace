<?php

namespace frontend\controllers;

use Yii;
use common\models\UserEfector;
use common\models\Persona;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use webvimark\modules\UserManagement\models\User;

/**
 * UserEfectorController implements the CRUD actions for UserEfector model.
 */
class UserEfectorController extends Controller {

    public function behaviors() {
        //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all UserEfector models.
     * @return mixed
     */
    public function actionIndex() {
        $dataProvider = new ActiveDataProvider([
            'query' => UserEfector::find(),
        ]);

        return $this->render('index', [
                    'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single UserEfector model.
     * @param integer $id_user
     * @param integer $id_efector
     * @return mixed
     */
    public function actionView($id_user, $id_efector) {
        return $this->render('view', [
                    'model' => $this->findModel($id_user, $id_efector),
        ]);
    }

    /**
     * Creates a new UserEfector model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate() {
        $model = new UserEfector();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_user' => $model->id_user, 'id_efector' => $model->id_efector]);
        } else {
            return $this->render('create', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing UserEfector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id_user
     * @param integer $id_efector
     * @return mixed
     */
    public function actionUpdate($id_user, $id_efector) {
        $model = $this->findModel($id_user, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_user' => $model->id_user, 'id_efector' => $model->id_efector]);
        } else {
            return $this->render('update', [
                        'model' => $model,
            ]);
        }
    }

    protected function encontrarModelo($id_user, $id_efector) {
        if (($model = UserEfector::findOne(['id_user' => $id_user, 'id_efector' => $id_efector])) !== null) {
            return $model;
        } else {
            return false;
        }
    }
    /**
     * Deletes an existing UserEfector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id_user
     * @param integer $id_efector
     * @return mixed
     */
    public function actionDelete($id_user, $id_efector) {
        $this->findModel($id_user, $id_efector)->delete();

        return $this->redirect(['index']);
    }


    /**
     * Finds the UserEfector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id_user
     * @param integer $id_efector
     * @return UserEfector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_user, $id_efector) {
        if (($model = UserEfector::findOne(['id_user' => $id_user, 'id_efector' => $id_efector])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}
