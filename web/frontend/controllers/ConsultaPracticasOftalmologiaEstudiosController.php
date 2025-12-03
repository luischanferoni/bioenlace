<?php

namespace frontend\controllers;

use common\models\ConsultaPracticasOftalmologiaEstudios;
use common\models\Consulta;
use common\models\ConsultaDerivaciones;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedProcedimientos;
use common\models\Turno;
use Yii;
use common\models\ConsultaPracticasOftalmologia;
use common\models\busquedas\ConsultaPracticasOftalmologiaBusqueda;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use frontend\controllers\DefaultController;
use yii\base\Exception;


/**
 * ConsultaPracticasOftalmologiaEstudiosController implements the CRUD actions for ConsultaPracticasOftalmologiaEstudios model.
 */
class ConsultaPracticasOftalmologiaEstudiosController extends DefaultController
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
    public function actionViewEstudiosComplementarios($id)
    {
        $arrayC = [86944008 => 'Campo visual', 397524001 => 'Retinoscopia', 19731001 => 'Ecografía', 113113000 => 'Ecometría'];
        return $this->render('viewMedico', [
            'model' => $this->findModel($id)
        ]);
    }

    public function createCore($modelConsulta)
    {

            return self::createEstudiosComplementarios($modelConsulta);
    }
    
    public function actionCreateCrud($id_consulta)
    {
        /* @var $consulta Consulta */
        $consulta= $this->findConsultaModel($id_consulta);
        return $this->createEstudiosComplementarios($consulta, $form_steps = false);
    }

     /**
     * Creates a new ConsultaPracticasOftalmologia model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function createEstudiosComplementarios($modelConsulta, $form_steps=true)
    {

        $arrayC = [86944008 => 'Campimetría', 397524001 => 'Retinoscopia', 19731001 => 'Estudio ecográfico de globo ocular', 113113000 => 'Ecometría'];
        $arrayO = ['OD' => 'OD', 'OI' => 'OI', 'AMBOS' => 'AMBOS' ];
        $id_consulta = Yii::$app->request->get('id_consulta');


        # Mínimo de childs, para el dynamic form
        $form_childs_min = 0;

        $oftalmologia_ids = [];
        $oftalmologias = $modelConsulta->oftalmologiasEstudios;
        #echo $modelConsulta->id_consulta.' <br> '.var_dump($oftalmologias);exit;

        if(!$oftalmologias) {
            #$oftalmologias = [new ConsultaPracticasOftalmologia()];
            list($oftalmologias, $diagnosticos) = $this->preCargarDerivaciones($modelConsulta);
            $form_childs_min = count($oftalmologias)>0 ? 1 : 0;
        } else {
            $oftalmologia_ids = ArrayHelper::getColumn($oftalmologias, 'id');
        }
        if (Yii::$app->request->post()) {
            $oftalmologias = FormularioDinamico::createAndLoadMultiple(
            ConsultaPracticasOftalmologiaEstudios::classname(),
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
                        SnomedProcedimientos::crearSiNoExiste($oftalmologia->codigo, Yii::$app->request->post("CustomAttribute")[$i]["termino_procedimiento"]);
                        $oftalmologia->id_consulta = $modelConsulta->id_consulta;
                        $oftalmologia->tipo = 1;
                        if(Yii::$app->request->post($i.'_rechazado')){
                            $consultaDerivaciones = ConsultaDerivaciones::findOne($oftalmologia->id_consultas_derivaciones);
                            $consultaDerivaciones->estado = ConsultaDerivaciones::ESTADO_RECHAZADA;
                            $consultaDerivaciones->id_respondido = $modelConsulta->id_consulta;
                            $consultaDerivaciones->save();
                        }else {
                            if (!$oftalmologia->save()) {
                                $msg = 'Error al guardar entidad ConsultaPracticasOftalmologia: '.$i;
                                throw new Exception($msg);
                            }
                            if($oftalmologia->id_consultas_derivaciones){
                                $consultaDerivaciones = ConsultaDerivaciones::findOne($oftalmologia->id_consultas_derivaciones);
                                $consultaDerivaciones->estado = ConsultaDerivaciones::ESTADO_RESUELTA;
                                $consultaDerivaciones->id_respondido = $oftalmologia->id;
                                $consultaDerivaciones->save();
                            }
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

    public function preCargarDerivaciones($modelConsulta)
    {
        $tieneDerivacion = false;
        if ($modelConsulta->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION]) {
            $tieneDerivacion = true;
        }

        // 1. La consulta viene de un turno
        if ($modelConsulta->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]) {
            $turno = Turno::findOne($modelConsulta->parent_id);
            // 2. El turno origen de la consulta viene de una derivacion
            if ($turno->parent_class === Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION]) {
                $tieneDerivacion = true;
            }
        }

        $oftalmologias = [];
        $diagnosticos = null;
        $derivaciones = [];

        if ($tieneDerivacion) {
            // 3. Pueden ser multiples derivaciones
            $derivaciones = ConsultaDerivaciones::getDerivacionesPorPersona($modelConsulta->id_persona, $modelConsulta->id_efector, $modelConsulta->id_servicio, ConsultaDerivaciones::ESTADO_CON_TURNO);
            // 4. Busco la consulta que solicito la/s derivaciones para obtener los diagnosticos originales
            if($derivaciones) {

                $consultaSolicitante = $derivaciones[0]->consulta;
                $diagnosticos = $consultaSolicitante->diagnosticoConsultas;

                // 5. Precargo el codigo de las practicas con las derivaciones
                foreach ($derivaciones as $derivacion) {
                    if ($derivacion->tipo_solicitud == ConsultaDerivaciones::PRACTICA) {
                        $consultaPracticaOf = new ConsultaPracticasOftalmologiaEstudios();
                        $consultaPracticaOf->codigo = $derivacion->codigo;
                        $consultaPracticaOf->estado = ConsultaPracticasOftalmologiaEstudios::ESTADO_COMPLETADA;
                        $consultaPracticaOf->id_consultas_derivaciones = $derivacion->id;
                        $consultaPracticaOf->codigo_deshabilitado = true;
                        $consultaPracticaOf->setIsNewRecord(false);
                        $oftalmologias[] = $consultaPracticaOf;
                    }
                }
            }
        }

        if (count($oftalmologias) == 0) {
            $oftalmologias = [new ConsultaPracticasOftalmologiaEstudios()];
        }

        if ($diagnosticos == null) {
            $diagnosticos = $modelConsulta->diagnosticoConsultas;
        }

        return [$oftalmologias, $diagnosticos];
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
