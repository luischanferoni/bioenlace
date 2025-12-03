<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Response;

use common\models\busquedas\RrhhEfectorBusqueda;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Persona;
use common\models\Servicio;

/**
 * RrhhEfectorController implements the CRUD actions for RrhhEfector model.
 */
class RrhhEfectorController extends Controller
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
     * Lists all RrhhEfector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RrhhEfectorBusqueda();

        if (!Yii::$app->user->getIdEfector()) {
            $searchModel->scenario = RrhhEfectorBusqueda::EFECTOR_SEARCH;
        }
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single RrhhEfector model.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id_rr_hh, $id_efector)
    {
        return $this->render('view', [
            'model' => $this->findModel($id_rr_hh, $id_efector),
        ]);
    }

    /**
     * Administra a la persona como administrador de Efector
     * Permite asignarle y quitarle la administrador de multiples Efectores
     * @return mixed
     */

    public function actionCreateAdminEfectorConRrhh($id)
    {
        $model = new RrhhEfector();

        $persona = Persona::find()->where(['id_user' => $id])->one();

        if (!$persona) {
            throw new NotFoundHttpException('Este usuario no posee una persona asociada');
        }
        $error = false;
        // Este es el servicio que le otorga el rol de AdminEfector
        // TODO: que el string AdminEfector venga de una constante
        $admin_efector_servicio = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();

        // Busco los efectores para los cuales la persona es AdminEfector
        $query_rrhh_efector = RrhhEfector::find();
        $rrhh_efectores = $query_rrhh_efector->andWhere(['id_persona' => $persona->id_persona])
            ->innerJoin(
                'rrhh_servicio',
                'rrhh_servicio.id_rr_hh = rrhh_efector.id_rr_hh' .
                    ' AND rrhh_servicio.id_servicio = ' . $admin_efector_servicio->id_servicio .
                    ' AND rrhh_servicio.deleted_at IS NULL'
            )
            ->asArray()->all();

        //echo $query_rrhh_efector->createCommand()->getRawSql();die;
        // array para el Select2
        $persona_efectores = ArrayHelper::getColumn($rrhh_efectores, 'id_efector');
        //var_dump($persona_efectores);die;
        if (Yii::$app->request->post()) {

            $transaction = \Yii::$app->db->beginTransaction();
            try {
                $post_efectores = Yii::$app->request->post('efectores') ? Yii::$app->request->post('efectores') : [];
                // obtengo los nuevos que vengan
                $rrhh_efectores_a_crear = array_diff($post_efectores, $persona_efectores);

                foreach ($rrhh_efectores_a_crear as $rrhh_efector_a_crear) {

                    $rrhh_efector = RrhhEfector::find()->where(['id_efector' => $rrhh_efector_a_crear, 'id_persona' => $persona->id_persona])->one();

                    if (!$rrhh_efector) {
                        $rrhh_efector = new RrhhEfector();
                        $rrhh_efector->id_efector = (int)$rrhh_efector_a_crear;
                        $rrhh_efector->id_persona = $persona->id_persona;

                        if (!$rrhh_efector->validate()) {
                            $error = $rrhh_efector->getErrorSummary(true);
                            throw new Exception;
                        }
                        $rrhh_efector->save(false);
                    }

                    $rrhh_servicio = new RrhhServicio();
                    $rrhh_servicio->id_rr_hh = $rrhh_efector->id_rr_hh;
                    $rrhh_servicio->id_servicio = $admin_efector_servicio->id_servicio;
                    if (!$rrhh_servicio->validate()) {
                        $error = $rrhh_servicio->getErrorSummary(true);
                        throw new Exception;
                    }
                    $rrhh_servicio->save(false);
                }
                // los que no vengan los elimino
                $rrhh_efectores_a_eliminar = array_diff($persona_efectores, $post_efectores);
                $persona_rrhh_efectores = ArrayHelper::map($rrhh_efectores, 'id_efector', 'id_rr_hh');
                if (count($rrhh_efectores_a_eliminar) > 0) {
                    foreach ($rrhh_efectores_a_eliminar as $rrhh_efector_a_eliminar) {
                        /*
                        $rr_hh = RrhhEfector::find()
                                ->andWhere([
                                    'id_efector' => $rrhh_efector_a_eliminar,
                                    'id_persona' => $persona->id_persona
                                    ])
                                ->all();
                        $rr_hh->delete();
                        */

                        $rr_hh_servicio = RrhhServicio::find()
                            ->andWhere(
                                [
                                    'id_rr_hh' => $persona_rrhh_efectores[$rrhh_efector_a_eliminar],
                                    'id_servicio' => $admin_efector_servicio->id_servicio
                                ]
                            )
                            ->one()
                            ->delete();
                    }
                }

                $transaction->commit();
                return $this->redirect(['user-management/user/view', 'id' => $id]);
            } catch (Exception $e) {
                //var_dump($e->getMessage());die;
                $transaction->rollBack();
            }
        }

        return $this->render('create_admin_efector', [
            'persona' => $persona,
            'persona_efectores' => $persona_efectores,
            'error' => $error,
        ]);
    }

    public function actionCreateAdminEfector($id_rr_hh)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $servicioAdminEfector = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();

        $adminEfector = RrhhServicio::find()->where(['id_rr_hh' => $id_rr_hh])->andWhere(['id_servicio' => $servicioAdminEfector->id_servicio])->one();

        if (!$adminEfector) {

            $rrhh_servicio = new RrhhServicio();
            $rrhh_servicio->id_rr_hh = $id_rr_hh;
            $rrhh_servicio->id_servicio = $servicioAdminEfector->id_servicio;
            if (!$rrhh_servicio->validate()) {
                $error = $rrhh_servicio->getErrorSummary(true);
                //throw new Exception;
                //$error = json_decode($e->getMessage());

                return Json::encode(['error' => true, 'message' => $error]);
            }
            $rrhh_servicio->save(false);

        }else{

            $adminEfector->deleted_at = NULL;
            $adminEfector->save();
            
        }

        return "ok";

    }

    public function actionRemoveAdminEfector($id_rr_hh)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $servicioAdminEfector = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();

        $rrhhServicio = RrhhServicio::find()->where(['id_rr_hh' => $id_rr_hh, 'id_servicio' => $servicioAdminEfector->id_servicio])->one();

        if ($rrhhServicio) {
            $rrhhServicio->delete();
        }

        return "ok";
    }

    /**
     * Updates an existing RrhhEfector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id_rr_hh, $id_efector)
    {
        $model = $this->findModel($id_rr_hh, $id_efector);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Funcion para crear el select dependiente de rrhh de un efector
     */
    public function actionPersonasLiveSearch($q = null, $idEfector)
    {
        $out = ['results' => ['id' => '', 'text' => '']];

        if (is_null($q)) {
            return $out;
        }

        $data = RrhhEfector::personasLiveSearch($q, $idEfector);

        return Json::encode(['results' => $data]);
    }

    /**
     * Deletes an existing RrhhEfector model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id_rr_hh, $id_efector)
    {
        $this->findModel($id_rr_hh, $id_efector)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the RrhhEfector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id_rr_hh
     * @param integer $id_efector
     * @return RrhhEfector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id_rr_hh, $id_efector)
    {
        if (($model = RrhhEfector::findOne(['id_rr_hh' => $id_rr_hh, 'id_efector' => $id_efector])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
