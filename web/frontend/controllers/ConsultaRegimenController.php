<?php

namespace frontend\controllers;

use Yii;
use common\models\ConsultaRegimen;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

use common\models\FormularioDinamico;
use common\models\Consulta;
use common\models\snomed\SnomedProcedimientos;
use frontend\controllers\DefaultController;


/**
 * ConsultaRegimenController implements the CRUD actions for ConsultaRegimen model.
 */
class ConsultaRegimenController extends DefaultController
{
    use \common\traits\PersistentParametersTrait;
    
    protected function definePersistentParameters(){
        return [ 'id_consulta' => null ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all ConsultaRegimen models.
     * @return mixed
     */
    public function actionIndex()
    {
        $id_consulta = $this->getPersistentParameter('id_consulta');
        $consulta = $this->findConsultaModel($id_consulta);
        
        $query = ConsultaRegimen::find()
                ->where(['id_consulta' => $id_consulta])
                ;
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        $context = [
            'dataProvider' => $dataProvider,
        ];
        $context = array_merge($context, $this->getPersistentParameters());

        return $this->render('index', $context);
    }

    /**
     * Displays a single ConsultaRegimen model.
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
    
    protected function getNewModel() {
        $model = new ConsultaRegimen();
        return $model;
    }

    public function actionCreateCrud($id_consulta)
    {
        /* @var $consulta Consulta */
        $consulta= $this->findConsultaModel($id_consulta);
        return $this->doActionCreate($consulta);
    }
    
    public function createCore($modelConsulta)
    {
        return $this->doActionCreate($modelConsulta, True);
    }
    
    protected function doActionCreate($consulta, $is_ajax=False)
    {
        $regimen_ids_viejos = [];
        $regimenes = $consulta->regimenes;
        
        if(!$regimenes) {
          $regimenes = [ $this->getNewModel() ];
        } else {
            $regimen_ids_viejos = ArrayHelper::getColumn($regimenes, 'id');
        }
        
        if (Yii::$app->request->post()) {
            $form_custom = Yii::$app->request->post("CustomAttribute");            
            
            $regimenes = FormularioDinamico::createAndLoadMultiple(
                ConsultaRegimen::classname(), 
                'id',
                $regimenes);
            foreach ($regimenes as $i => $regimen) {
                $regimen->id_consulta = $consulta->id_consulta;
            }
            
            $valid = $consulta->isNewRecord?
                    $consulta->save():
                    $consulta->validate();
            $valid = FormularioDinamico::validateMultiple(
                    $regimenes, ['id'])
                    && $valid;
            if($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    foreach ($regimenes as $i => $regimen)
                    {
                        $term = $form_custom[$i]["termino_procedimiento"];
                        SnomedProcedimientos::crearSiNoExiste(
                            $regimen->concept_id, $term);
                        
                        if (!$regimen->save()) {
                            $msg = 'Error al guardar entidad ConsultaRegimen: '.$i;
                            throw new \Exception($msg);
                        }
                    }
                    $regimen_ids_guardar = ArrayHelper::getColumn($regimenes, 'id');
                    $regimen_ids_eliminar = array_diff($regimen_ids_viejos, $regimen_ids_guardar);
                    if (count($regimen_ids_eliminar) > 0) {
                        // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                        ConsultaRegimen::hardDeleteGrupo($consulta->id_consulta, $regimen_ids_eliminar);                        
                    }
                    $transaction->commit();
                    $response = null;
                    $is_ajax = Yii::$app->request->post('from_step_forms');
                    # Yii::error("Is_ajax:".$is_ajax);
                    if(null !== $is_ajax) {
                      $response = [
                          'success' => true,
                          'msg' => 'Los Regimenes fueron cargados exitosamente.',
                          'url_siguiente' => $consulta->urlSiguiente
                      ];
                    }
                    else {
                      $response = $this->redirect([
                        'consulta-regimen/index',
                        'id_consulta' => $consulta->id_consulta
                        ]);
                    }
                    return $response;

                } catch (\Exception $e) {
                    $transaction->rollBack();
                }
            }
        }

        $context = [
            'consulta' => $consulta,
            'regimenes' => $regimenes,
            'is_ajax' => $is_ajax,
        ];
        $render_func = $is_ajax? 'renderAjax': 'render';
        $template = $is_ajax? '_form': 'create';
        return $this->{$render_func}($template, $context);
    }

    /**
     * Deletes an existing ConsultaRegimen model.
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
     * Finds the ConsultaRegimen model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ConsultaRegimen the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultaRegimen::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
    
    protected function findConsultaModel($id)
    {
        if (($model = Consulta::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested Consulta Model does not exist.');
    }
}
