<?php

namespace frontend\controllers;

use Yii;
use common\models\Persona_mails;
use common\models\Persona;
use common\models\busquedas\Persona_mailsBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * PersonasMailsController implements the CRUD actions for persona_mails model.
 */
class PersonasMailsController extends Controller
{
    public function behaviors()
    {
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
     * Lists all persona_mails models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new Persona_mailsBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single persona_mails model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new persona_mails model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($idp)
    {
        $model = new Persona_mails();
        $persona = new Persona();
        $model_persona = $persona::findOne($idp);
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->redirect(['personas/view', 'id' => $model->id_persona]);
        } else {
//            $mje=$model->getErrors();
            return $this->render('create', [
                'model' => $model,
                 'model_persona' => $model_persona,
               
            ]);
        }
    }

    /**
     * Updates an existing persona_mails model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->redirect(['personas/view', 'id' => $model->id_persona]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing persona_mails model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the persona_mails model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return persona_mails the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Persona_mails::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
