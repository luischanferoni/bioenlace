<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;

use common\models\Consulta;
use common\models\ConsultaBalanceHidrico;
use common\models\FormularioDinamico;
use frontend\controllers\DefaultController;

/**
 * ConsultaBalanceHidricoController implements the CRUD actions for ConsultaBalanceHidrico model.
 */
class ConsultaBalanceHidricoController extends DefaultController
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
     * Lists all SegNivelInternacionBalancehidrico models.
     * @return mixed
     */
    public function actionIndex()
    {
        $id_consulta = $this->getPersistentParameter('id_consulta');
        $consulta = $this->findConsultaModel($id_consulta);
        
        $query = ConsultaBalancehidrico::find()
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
     * Displays a single model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $context = [
            'model' => $this->findModel($id),
        ];
        $context = array_merge($context, $this->getPersistentParameters());
        return $this->render('view', $context);
    }
    
    
    protected function getNewModel() {
        $model = new ConsultaBalanceHidrico();
        
        $now = new \DateTime();
        $model->fecha = $now->format('d/m/Y');
        $model->tipo_registro = ConsultaBalanceHidrico::TREG_INGRESO;
        $model->hora_inicio = $now->format('G:i');
        $now->modify('+30 minutes');
        $model->hora_fin = $now->format('G:i');
        
        return $model;
    }

    /**
     * Creates a new SegNivelInternacionBalancehidrico model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
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
        $balances = $consulta->balancesHidricos;
        
        if(!$balances) {
          $balances = [ $this->getNewModel() ];
        }
        
        if (Yii::$app->request->post()) {
            $balance_ids_viejos = ArrayHelper::getColumn($balances, 'id');
            
            $balances = FormularioDinamico::createAndLoadMultiple(
                ConsultaBalanceHidrico::classname(), 
                'id',
                $balances);
            foreach ($balances as $i => $balance) {
                $balance->id_consulta = $consulta->id_consulta;
            }
            
            $valid = $consulta->isNewRecord?
                    $consulta->save():
                    $consulta->validate();
            $valid = FormularioDinamico::validateMultiple(
                    $balances, ['id'])
                    && $valid;
            if($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    foreach ($balances as $i => $balance) {
                        if (!$balance->save()) {
                            $msg = 'Error al guardar entidad BalanceHidrico: '.$i;
                            throw new \Exception($msg);
                        }
                    }
                    $balance_ids_guardar = ArrayHelper::getColumn($balances, 'id');
                    $balance_ids_eliminar = array_diff($balance_ids_viejos, $balance_ids_guardar);
                    if (count($balance_ids_eliminar) > 0) {
                        // hard delete
                        ConsultaBalanceHidrico::hardDeleteGrupo($consulta->id_consulta, $balance_ids_eliminar);
                    }
                    $transaction->commit();
                    $response = null;
                    $is_ajax = Yii::$app->request->post('from_step_forms');
                    Yii::error("Is_ajax:".$is_ajax);
                    if(null !== $is_ajax) {
                      $response = [
                          'success' => true,
                          'msg' => 'Los balances fueron cargados exitosamente.',
                          'url_siguiente' => $consulta->urlSiguiente
                      ];
                    }
                    else {
                      $response = $this->redirect([
                        'consulta-balance-hidrico/index',
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
            'balances' => $balances,
            'is_ajax' => $is_ajax,
        ];
        $render_func = $is_ajax? 'renderAjax': 'render';
        $template = $is_ajax? '_form': 'create';
        return $this->{$render_func}($template, $context);
    }

    /**
     * Deletes an existing SegNivelInternacionBalancehidrico model.
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
     * Finds the SegNivelInternacionBalancehidrico model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SegNivelInternacionBalancehidrico the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultaBalanceHidrico::findOne($id)) !== null) {
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
