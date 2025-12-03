<?php

namespace frontend\controllers;

use common\models\Consulta;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedProcedimientos;
use Yii;
use common\models\ConsultaPracticasOftalmologia;
use common\models\busquedas\ConsultaPracticasOftalmologiaBusqueda;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use frontend\controllers\DefaultController;
use yii\base\Exception;


/**
 * ConsultaPracticasOftalmologiaController implements the CRUD actions for ConsultaPracticasOftalmologia model.
 */
class ConsultaPracticasOftalmologiaController extends DefaultController
{
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

    protected function findConsultaModel($id)
    {
        if (($model = Consulta::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested Consulta Model does not exist.');
    }

    /**
     * Lists all ConsultaPracticasOftalmologia models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ConsultaPracticasOftalmologiaBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ConsultaPracticasOftalmologia model.
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
     * Displays a single ConsultaPracticasOftalmologia model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionViewMedico($id)
    {
        $arrayC = [252832004 => 'Presión intraocular', 252886007 => 'Refracción', 55468007 => 'Lámpara de hendidura', 410455004 => 'Fondo de ojo con lámpara de hendidura'];
        return $this->render('viewMedico', [
            'model' => $this->findModel($id)
        ]);
    }

    public function createCore($modelConsulta)
    {
            return self::createMedico($modelConsulta);
    }
    
    public function actionCreateCrud($id_consulta)
    {
        /* @var $consulta Consulta */
        $consulta= $this->findConsultaModel($id_consulta);
        return $this->createMedico($consulta, $form_steps = false);
    }

    /**
     * Creates a new ConsultaPracticasOftalmologia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function createMedico($modelConsulta, $form_steps=true)
    {
        //$arrayC = [252832004 => 'Presión intraocular', 252886007 => 'Refracción', 55468007 => 'Lámpara de hendidura', 410455004 => 'Fondo de ojo con lámpara de hendidura'];
        $arrayO = ['OD' => 'OD','OI' => 'OI', 'AMBOS' => 'AMBOS' ];

        # Mínimo de childs, para el dynamic form
        $form_childs_min = 0;

        $oftalmologia_ids = [];
        $oftalmologias = $modelConsulta->oftalmologias;

        if(!$oftalmologias) {
            $oftalmologias = [new ConsultaPracticasOftalmologia()];
        } else {
            $oftalmologia_ids = ArrayHelper::getColumn($oftalmologias, 'id');
        }

        if (Yii::$app->request->post()) {           

            $oftalmologias = FormularioDinamico::createAndLoadMultiple(
                ConsultaPracticasOftalmologia::classname(),
                'id',
                $oftalmologias);


            foreach ($oftalmologias as $i => $oftalmologia) {
                $oftalmologia->id_consulta = $modelConsulta->id_consulta;
                $oftalmologia->tipo = 0;
                if ($oftalmologia->codigo == 164729009):
                    $oftalmologia->scenario = ConsultaPracticasOftalmologia::SCENARIOTIPOGRUPO1;
                else:
                    $oftalmologia->scenario = ConsultaPracticasOftalmologia::SCENARIOTIPOGRUPO2;
                endif;
            }

            $valid = $modelConsulta->isNewRecord ?
                $modelConsulta->save() :
                $modelConsulta->validate();
            $valid = FormularioDinamico::validateMultiple($oftalmologias)
                && $valid;
            if ($valid) {
                $modelConsulta->save() ;
                $transaction = \Yii::$app->db->beginTransaction();
                $modelConsulta->save() ;
                try {
                    foreach ($oftalmologias as $i => $oftalmologia) {
                        if (!$oftalmologia->save()) {
                            $msg = 'Error al guardar entidad ConsultaPracticasOftalmologia: ' . $i;
                            throw new Exception($msg);
                        }
                    }
                    $oftalmologia_ids_guardar = ArrayHelper::getColumn($oftalmologias, 'id');
                    $oftalmologia_ids_eliminar = array_diff($oftalmologia_ids, $oftalmologia_ids_guardar);
                    if (count($oftalmologia_ids_eliminar) > 0) {
                        // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                        ConsultaPracticasOftalmologia::hardDeleteGrupo($modelConsulta->id_consulta, $oftalmologia_ids_eliminar);                        
                    }
                    $transaction->commit();

                      $response = [
                        'success' => true,
                        'msg' => 'Los resultados fueron cargados exitosamente.',
                        'url_siguiente' => $modelConsulta->urlSiguiente
                        ];

                          return $response;

                } catch (Exception $e) {
                    if ($e->getMessage() != "") {
                        Yii::error($e->getMessage());
                    }
                    $transaction->rollBack();
                }
            } else {
              # El form tiene errores
              $form_childs_min = 1;
              # Fijo el minimo de childs, para que el dynamic form
              # no los borre si todos son nuevos. WTF!!!!!!
            }
        }

        $context = [
            'modelConsulta' => $modelConsulta,
            'oftalmologias' => $oftalmologias,
            //'arrayC' => $arrayC,
            'arrayO' => $arrayO,
            'form_steps' => $form_steps,
            'form_childs_min' => $form_childs_min,
        ];

        $render_func = $form_steps? 'renderAjax': 'render';
        $template = $form_steps? '_formMedico': 'createMedico';
        return $this->{$render_func}($template, $context);

    }

    /**
     * Creates a new ConsultaPracticasOftalmologia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreateEstudiosComplementarios($id_consulta, $form_steps=true)
    {

        $arrayC = [86944008 => 'Campo visual', 397524001 => 'Retinoscopia', 19731001 => 'Ecografía', 113113000 => 'Ecometría'];
        $arrayO = [ 'OI' => 'OI', 'OD' => 'OD', 'AMBOS' => 'AMBOS' ];
        $id_consulta = Yii::$app->request->get('id_consulta');
        $modelConsulta = $this->findConsultaModel($id_consulta);

        # Mínimo de childs, para el dynamic form
        $form_childs_min = 0;

        $oftalmologia_ids = [];
        $oftalmologias = $modelConsulta->oftalmologiasEstudios;

        if(!$oftalmologias) {
            $oftalmologias = [new ConsultaPracticasOftalmologia()];
        } else {
            $oftalmologia_ids = ArrayHelper::getColumn($oftalmologias, 'id');
        }

        if (Yii::$app->request->post()) {

            $oftalmologias = FormularioDinamico::createAndLoadMultiple(
            ConsultaPracticasOftalmologia::classname(),
            'id',
            $oftalmologias);

            $valid = $modelConsulta->isNewRecord ?
                $modelConsulta->save() :
                $modelConsulta->validate();
            $valid = FormularioDinamico::validateMultiple($oftalmologias)
                && $valid;
            if ($valid) {
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    foreach ($oftalmologias as $i => $oftalmologia) {
                        $oftalmologia->id_consulta = $modelConsulta->id_consulta;
                        $oftalmologia->tipo = 1;
                        if (!$oftalmologia->save()) {
                            $msg = 'Error al guardar entidad ConsultaPracticasOftalmologia: '.$i;
                            throw new Exception($msg);
                        }
                    }
                    $oftalmologia_ids_guardar = ArrayHelper::getColumn($oftalmologias, 'id');
                    $oftalmologia_ids_eliminar = array_diff($oftalmologia_ids, $oftalmologia_ids_guardar);
                    if (count($oftalmologia_ids_eliminar) > 0) {
                        // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
                        ConsultaPracticasOftalmologia::hardDeleteGrupo($modelConsulta->id_consulta, $oftalmologia_ids_eliminar);
                    }
                    $transaction->commit();
                    $form_steps = Yii::$app->request->post('form_steps');
                    if(null !== $form_steps) {
                        $response = [
                            'success' => true,
                            'msg' => 'Los resultados fueron cargados exitosamente.',
                            'url_siguiente' => $modelConsulta->urlSiguiente . '?id_consulta=' . $modelConsulta->id_consulta
                        ];
                    }

                } catch (Exception $e) {
                    if ($e->getMessage() != "") {
                        Yii::error($e->getMessage());
                    }

                    $transaction->rollBack();

                    return $this->renderAjax('createMedico', [
                        'modelConsulta' => $modelConsulta,
                        'oftalmologias' => $oftalmologias,
                        'arrayC' => $arrayC,
                        'arrayO' => $arrayO,
                        'form_steps' => $form_steps,
                        'form_childs_min' => $form_childs_min,
                    ]);
                }
            } else {
                # El form tiene errores
                $form_childs_min = 1;
                # Fijo el minimo de childs, para que el dynamic form
                # no los borre si todos son nuevos. WTF!!!!!!
            }
        }

        $context = [
            'modelConsulta' => $modelConsulta,
            'oftalmologias' => $oftalmologias,
            'arrayC' => $arrayC,
            'arrayO' => $arrayO,
            'form_steps' => $form_steps,
            'form_childs_min' => $form_childs_min,
        ];
        $render_func = $form_steps? 'renderAjax': 'render';
        $template = $form_steps? '_formEstudiosComplementarios': 'createEstudiosComplementarios';
        return $this->{$render_func}($template, $context);

    }

    /**
     * Updates an existing ConsultaPracticasOftalmologia model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdateMedico($id)
    {
        $model = $this->findModel($id);
        $arrayC = [252832004 => 'Presión intraocular', 252886007 => 'Refracción', 55468007 => 'Lámpara de hendidura', 410455004 => 'Fondo de ojo con lámpara de hendidura'];

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if( in_array('no-evaluar',  array_keys(Yii::$app->request->post()) )):
                $model->resultado = 'no se puedo evaluar';
                $model->save();
            endif;
            return $this->redirect(['view-medico', 'id' => $model->id]);
        }

        return $this->render('updateMedico', [
            'model' => $model, 'arrayC' => $arrayC
        ]);
    }

    /**
     * Updates an existing ConsultaPracticasOftalmologia model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if( in_array('no-copera',  array_keys(Yii::$app->request->post()) )):
                $model->resultado = 'no-copera';
                $model->save();
            endif;
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ConsultaPracticasOftalmologia model.
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
     * Finds the ConsultaPracticasOftalmologia model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ConsultaPracticasOftalmologia the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ConsultaPracticasOftalmologia::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
