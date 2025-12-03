<?php

namespace frontend\controllers;

use Yii;
use common\models\Domicilio;
use common\models\Localidad;
use common\models\Departamento;
use common\models\Provincia;
use common\models\Persona;
use common\models\Persona_domicilio;
use common\models\busquedas\DomicilioBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * DomiciliosController implements the CRUD actions for domicilio model.
 */
class DomiciliosController extends Controller
{
    public function behaviors()
    {
        return [
            /*'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],*/
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all domicilio models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DomicilioBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single domicilio model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model_persona_domicilio = new Persona_domicilio();
        $model_persona = new Persona();
        $model_localidad = new Localidad();

        return $this->render('view', [
                    'model' => $this->findModel($id),
                    'model_persona' => $model_persona,
                    'model_persona_domicilio' => $model_persona_domicilio,
                    'model_localidad' => $model_localidad,
        ]);
    }
    public function actionView_nuevo_domicilio($id)
    {
        $model_persona_domicilio = new Persona_domicilio();
        $model_persona = new Persona();
        $model_localidad = new Localidad();

        return $this->render('view_create', [
                    'model' => $this->findModel($id),
                    'model_persona' => $model_persona,
                    'model_persona_domicilio' => $model_persona_domicilio,
                    'model_localidad' => $model_localidad,
        ]);
    }

    /**
     * Creates a new domicilio model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($idp)
    {
        $model = new domicilio();
        $persona = new Persona();
        $model_persona = $persona::findOne($idp);
        $model_persona_domicilio = new Persona_domicilio();
        $model_localidad = new Localidad();
        $model_departamento = new Departamento();
        $model_provincia = new Provincia();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            $transaction = \Yii::$app->db->beginTransaction();
            try {
                $model->save();

                $model_persona_domicilio = new Persona_domicilio();
                $model_persona_domicilio->id_persona = $idp;
                $model_persona_domicilio->id_domicilio = $model->id_domicilio;
                $model_persona_domicilio->activo = 'SI';
                $model_persona_domicilio->usuario_alta = Yii::$app->user->username;
                $model_persona_domicilio->fecha_alta = date('Y-m-d');
                $model_persona_domicilio->save();

                $transaction->commit();

                $this->redirect(['personas/view', 'id' => $idp]);
            } catch(Exception $e) {
                $transaction->rollBack();
            }


        } else {

            return $this->render('create', [
                'model' => $model,
                'model_persona' => $model_persona,
                'model_persona_domicilio' => $model_persona_domicilio,
                'model_localidad' => $model_localidad,
                'model_departamento' => $model_departamento,
                'model_provincia' => $model_provincia,

            ]);

        }
    }

    /**
     * Updates an existing domicilio model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id, $idp)
    {
        $model = $this->findModel($id);
        $model_persona = new Persona();
        $model_persona_domicilio = new Persona_domicilio();
        $persona = $model_persona::findOne($idp);

        $model_localidad = new Localidad();
        $model_provincia = new Provincia();
        $model_departamento = new Departamento();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->redirect(['personas/view', 'id' => $idp]);
        } else {
            return $this->render('update', [
                'model' => $model,
                'model_persona' => $persona,
                'model_persona_domicilio' => $model_persona_domicilio,
                'model_localidad' => $model_localidad,
                                'model_departamento' => $model_departamento,
                'model_provincia' => $model_provincia,
            ]);
        }
    }

    /**
     * Deletes an existing domicilio model.
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
     * Finds the domicilio model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return domicilio the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = domicilio::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
