<?php

namespace backend\controllers;

use Yii;
use common\models\InfraestructuraSala;
use common\models\busquedas\InfraestructuraSalaBusqueda;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\filters\AccessControl;
use yii\filters\AccessRule;

/**
 * InfraestructuraSalaController implements the CRUD actions for InfraestructuraSala model.
 */
class InfraestructuraSalaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],            
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],        
        ];
    }

    /**
     * Lists all InfraestructuraSala models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new InfraestructuraSalaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single InfraestructuraSala model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new InfraestructuraSala model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new InfraestructuraSala();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing InfraestructuraSala model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing InfraestructuraSala model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the InfraestructuraSala model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return InfraestructuraSala the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = InfraestructuraSala::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

     /**
     * 
     * Funcion para crear el select dependiente de Departamentos
     */
    public function actionSalasPorPiso() {
        $out = [];
        $prueba = json_encode($_POST['depdrop_parents']);

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $cat_id = $parents[0];
                $out = InfraestructuraSala::find()->asArray()
                ->select(['id' => 'id', 'name' => 'descripcion'])
                ->where(['id_piso' => $cat_id])
                ->all();  
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        if (isset($_POST['id_piso'])) {
            $countSalas = InfraestructuraSala::find()
            ->where(['id_piso' => $_POST['id_piso']])
            ->count();
            Yii::trace("SalasPorPiso 3: ".$countSalas);
            $salas = InfraestructuraSala::find()
            ->where(['id_piso' => $_POST['id_piso']])
            ->all();
            if ($countSalas > 0) {
                foreach ($salas as $sala) {
                    $selected = ($sala->id == $_POST['id']) ? "selected" : "";
                    echo "<option value='$sala->id' $selected >" . $sala->descripcion . "</option>";
                }
            }
            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }
}
