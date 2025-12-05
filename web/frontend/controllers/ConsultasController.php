<?php

namespace frontend\controllers;

use Codeception\Lib\Di;
use Yii;

use yii\web\Controller;

use yii\filters\VerbFilter;
use yii\data\SqlDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;

use yii\web\NotFoundHttpException;
use yii\base\UnknownPropertyException;

use common\models\Consulta;
use common\models\ConsultasConfiguracion;
use common\models\busquedas\ConsultaBusqueda;
use common\models\ConsultaAtencionesEnfermeria;

use common\models\ValoracionNutricional;
use common\models\TensionArterial;
use common\models\Persona;
use Exception;
use frontend\components\UserRequest;

/**
 * ConsultasController implements the CRUD actions for Consulta model.
 */
class ConsultasController extends Controller
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
     * Lists all Consulta models.
     * @category Consultas
     * @tags consulta,atencion,listar,ver todos
     * @keywords listar,ver todos,mostrar,consultas,atenciones
     * @synonyms consulta,atencion,visita,cita médica
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ConsultaBusqueda();
        $searchModel->id_efector = Yii::$app->user->getIdEfector();
        $dataProvider = $searchModel->searchGral(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Crear una nueva consulta
     * @category Consultas
     * @tags consulta,atencion,crear,nuevo
     * @keywords crear,nuevo,agregar,consulta,atencion
     * @synonyms consulta,atencion,visita
     */
    public function actionCreate()
    {

        $session = Yii::$app->getSession();
        $paciente = unserialize($session->get('persona'));

        if ($paciente->estadoPaciente == Persona::ESTADO_ESPERANDO_TURNO) {
        }

        if ($paciente->estadoPaciente == Persona::ESTADO_INTERNADA) {
        }

        Yii::$app->request->get('id_servicio');
    }

    /**
     * Actualizar una consulta existente
     * @category Consultas
     * @tags consulta,atencion,editar,modificar,actualizar
     * @keywords editar,modificar,actualizar,consulta
     * @synonyms consulta,atencion
     */
    public function actionUpdate()
    {
        $idConsulta = Yii::$app->request->get('id_consulta');
        $id_persona = Yii::$app->request->get('id_persona');

        $modelConsulta = $this->findModel($idConsulta);

        try {
            $idRrhh = UserRequest::requireUserParam('idRecursoHumano');
        } catch (\yii\web\BadRequestHttpException $e) {
            Yii::$app->session->setFlash('error', 'Falta parámetro idRecursoHumano');
            return $this->redirect(['historialconsultas']);
        }

        if ($idRrhh != $modelConsulta->id_rr_hh) {
            Yii::$app->session->setFlash('error', 'No tiene autorizacion para editar esta consulta');
            return $this->redirect(['historialconsultas']);
        }

        if (Yii::$app->request->post()) {

            $idNuevaConsulta = $this->clonar($modelConsulta);

            list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($modelConsulta->id_configuracion, -1);

            if ($urlSiguiente == null) {
                $urlSiguiente = 'fin';
            }

            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            return [
                'success' => true,
                'url_siguiente' => $urlSiguiente . ($urlSiguiente == 'fin' ? '' : '?id_consulta=' . $idNuevaConsulta . '&id_persona=' . $id_persona)
            ];
        }


        return $this->renderAjax('update', ['consulta' => $modelConsulta]);
    }

    public function actionContinuarConsulta()
    {
        $idConsulta = Yii::$app->request->get('id_consulta');
        $continuar = Yii::$app->request->get('continuar');
        $id_persona = Yii::$app->request->get('id_persona');
        list($idConfiguracion, $paso) = explode("-", $continuar);

        // paso_completado al comienzo se guarda en 0 en BD        
        list($urlAnterior, $urlActual, $urlSiguiente) = ConsultasConfiguracion::getUrlPorIdConfiguracion($idConfiguracion, $paso - 1);

        if ($urlSiguiente == null) {
            $urlSiguiente = 'fin';
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        return [
            'success' => '',
            'url_siguiente' => $urlSiguiente . ($urlSiguiente == 'fin' ? '' : '?id_consulta=' . $idConsulta . '&id_persona=' . $id_persona)
        ];
    }

    /**
     * Lists all Consulta models.
     * @return mixed
     */
    public function actionListadoSumar()
    {

        $id_user = Yii::$app->request->get('id_user') ? Yii::$app->request->get('id_user') : Yii::$app->user->id;
        $fecha = Yii::$app->request->get('fecha') ? '"' . Yii::$app->request->get('fecha') . '"' : 'CURRENT_DATE()';
        // if(!User::hasRole('MedicoAdmin')){$fecha = 'CURRENT_DATE()';}
        $idEfector = Yii::$app->user->getIdEfector();

        $dataProvider = new SqlDataProvider([
            'key' => 'id_turnos',
            'sql' => 'SELECT turnos.atendido, turnos.hora, turnos.confirmado, '
                . 'turnos.id_turnos as id_turnos, pac.apellido, pac.nombre, pac.documento, '
                . 'turnos.hora, pac.id_persona, rr_hh.id_rr_hh, IF (phc.numero_hc IS NULL, "--", phc.numero_hc) AS numeroHC, '
                . 'turnos.programado FROM `personas` '
                . 'INNER JOIN rr_hh ON (personas.id_persona = rr_hh.id_persona) '
                . 'INNER JOIN turnos ON (rr_hh.id_rr_hh = turnos.id_rr_hh) '
                . ''
                . 'INNER JOIN personas pac ON (turnos.id_persona = pac.id_persona) '
                . 'LEFT JOIN personas_hc phc ON (phc.id_persona = turnos.id_persona) '
                . 'WHERE personas.id_user = :id_user '
                . 'AND turnos.fecha =' . $fecha . ' '
                . 'AND turnos.id_efector = :id_efector '
                . 'AND turnos.atendido IS NULL GROUP BY turnos.id_turnos ORDER BY turnos.hora ASC ',
            'params' => [':id_user' => $id_user, ':id_efector' => $idEfector]
        ]); //AND rr_hh.id_especialidad = 1 
        if (Yii::$app->request->get('id_user')) {
            $this->layout = 'imprimir';
        }
        return $this->render('listado_sumar', ['dataProvider' => $dataProvider]);
    }

    //Index Historial
    public function actionHistorialconsultas()
    {
        $searchModel = new ConsultaBusqueda();
        $searchModel->id_persona = Yii::$app->getRequest()->getQueryParam('id');

        $por_mpi = false;
        if (Yii::$app->getRequest()->getQueryParam('mpi_id') != null) {
            $por_mpi = true;
            $id_persona = Yii::$app->getRequest()->getQueryParam('mpi_id');
        }
        // Consultamos en Suri por las inmunizaciones del paciente

        // A modode prueba seteamos de nuevo las variables
        // TODO: modificar 280295 por $id_persona cuando suri este en produccion
        //$id_persona = 280295;
        //$por_mpi = false;
        //------------------
        $result = Yii::$app->suri->beneficiario($id_persona, $por_mpi);
        $inmunizacionesDataProvider = new ArrayDataProvider([
            'allModels' => $result,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => ['descripcion_snomed', 'fecha', 'numero_lote', 'nombre_inmunizacion', 'nombre_efector'],
            ],
        ]);

        // Consultas
        $dataProvider = $searchModel->searchConsultasPersona(Yii::$app->request->queryParams);

        return $this->renderAjax('historialconsultas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'inmunizacionesDataProvder' => $inmunizacionesDataProvider
        ]);
    }

    //Index Historial Antecedentes
    public function actionHistorialantecedentes()
    {
        $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $persona = Persona::findOne($id_persona);
        $antecedentes = $persona->antecedentes;

        $dataProvider = new ActiveDataProvider([
            'query' => $persona->getAntecedentes(),
            'pagination' => [
                'pageSize' => 5,
            ],
        ]);

        return $this->renderAjax('historialantecedentes', [
            'dataProvider' => $dataProvider,
            'persona' => $persona,
        ]);
    }

    public function actionViewDetail($id)
    {
        $consulta = $this->findModel($id);

        $context = [
            'consulta' => $consulta,
        ];
        return $this->render('detail', ['context' => $context]);
    }

    /**
     * Displays a single Consulta model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id_consulta)
    {
        $idConsulta = $id_consulta;
        $consulta =  $this->findModel($idConsulta);
        $modelosConsultaDiagnostico = $consulta->diagnosticoConsultas;
        $model_valoracion_nutricional = ValoracionNutricional::getValoracionPorConsulta($idConsulta);
        $model_tension_arterial = TensionArterial::getTensionPorConsulta($idConsulta);

        //join consultas y turnos
        # FIXME: Revisar asociacion de consulta y persona
        $id_persona = $consulta->id_persona;
        if ($consulta->turno) {
            $id_persona = $consulta->turno->id_persona;
        }
        $model_persona                          = Persona::findOne(['id_persona' => $id_persona]);
        $model_embarazo                         = $consulta->consultaObstetricia; #  ConsultaObstetricia
        $modelosConsultaMedicamentos            = $consulta->consultaMedicamentos;
        $modelosConsultaPracticas               = $consulta->consultaPracticas;
        $modelosConsultaEvaluaciones            = $consulta->consultaEvaluaciones;
        $modelosConsultaAntecedentesPersonales  = $consulta->personasAntecedenteConsultas;
        $modelosConsultaAntecedentesFamiliares  = $consulta->personasAntecedenteFamiliarConsultas;
        $modelosConsultaAlergias                = $consulta->alergias;
        $modelosConsultaDerivaciones            = $consulta->derivacionesSolicitadas;
        $modeloConsultaEvolucion                = $consulta->consultaEvolucion;
        $modelConsultaOftalmologia               = $consulta->getOftalmologiasDP();
        $modelConsultaOftalmologiaEstudios       = $consulta->getOftalmologiasEstudiosDP();
        $modelConsultaRecetaLente               = $consulta->recetasLentes;

        $consulta_fecha = '';
        $atencion_enfermeria = Null;
        if ($consulta->turno) {
            $consulta_fecha = $consulta->turno->fecha;
            $atencion_enfermeria = ConsultaAtencionesEnfermeria::obtenerValoracionNutricionalPorIdConsulta($consulta->id_consulta);
        }

        $context = [
            'model'                                 => $consulta,
            'model_persona'                         => $model_persona,
            'model_diagnosticos_consulta'           => $modelosConsultaDiagnostico,
            'atencion_enfermeria'                   => $atencion_enfermeria,
            'model_valoracion_nutricional'          => $model_valoracion_nutricional,
            'model_embarazo'                        => $model_embarazo,
            'model_tension_arterial'                => $model_tension_arterial,
            'model_medicamentos_consulta'           => $modelosConsultaMedicamentos,
            'model_consulta_practicas'              => $modelosConsultaPracticas,
            'model_consulta_evaluaciones'           => $modelosConsultaEvaluaciones,
            'model_consulta_alergias'               => $modelosConsultaAlergias,
            'model_consulta_derivaciones'           => $modelosConsultaDerivaciones,
            'model_personas_antecedente'            => $modelosConsultaAntecedentesPersonales,
            'model_personas_antecedente_familiar'   => $modelosConsultaAntecedentesFamiliares,
            'model_consulta_evolucion'              => $modeloConsultaEvolucion,
            'consulta_fecha'                        => $consulta_fecha,
            'model_consulta_oftalmologia'           => $modelConsultaOftalmologia,
            'model_consulta_oftalmologia_estudio'   => $modelConsultaOftalmologiaEstudios,
            'model_consulta_receta_lente'           => $modelConsultaRecetaLente,
            'show_button_bar'                       => true,
            'show_header'                           => true,
        ];

        if (Yii::$app->getRequest()->isAjax) {
            $context['show_button_bar'] = false;
            $context['show_header'] = false;
            return $this->renderAjax('view', $context);
        }
        return $this->render('view', $context);
    }

    public $freeAccessActions = ['imprimirreceta', 'prescripciones-medicas-por-consulta'];

    /**
     * Displays a single Consulta model.
     * @param string $id
     * @return mixed
     */
    public function actionImprimirreceta($id)
    {
        $consulta = $this->findModel($id);

        return $this->renderPartial('_imprimir_receta', [
            'model' => $consulta,
        ]);
    }

    /**
     * Deletes an existing Consulta model.
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
     * Finds the Consulta model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Consulta the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Consulta::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionObjetos($id)
    {
        $rows = Consulta::getObjetosPrestacion($id);

        $droptions = "<option>Seleccione un objeto</option>";

        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $droptions .= '<option value=' . $row['codigo'] . '>' . $row['nombre'] . '</option>';
            }
        } else {
            $droptions .= "<option>No results found</option>";
        }

        return $droptions;
    }

    public function actionPrescripcionesMedicasPorConsulta()
    {
        $dataProvider = Consulta::getConsultasConPrescripcion();

        return $this->render('prescripciones_medicas', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function clonar($modelConsulta)
    {
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $idConsulta = $modelConsulta->id_consulta;
            $nuevaConsulta = new Consulta();
            $atributos = $modelConsulta->attributes;
            $error = false;
            foreach ($atributos as  $atributo => $valor) {
                $nuevaConsulta->{$atributo} = $valor;
            }

            $nuevaConsulta->editando = $idConsulta;
            $nuevaConsulta->id_consulta = null;
            $nuevaConsulta->isNewRecord = true;
            // va en cero porque guardo
            $nuevaConsulta->paso_completado = 0;
            $nuevaConsulta->updated_at = date('Y-m-d H:i:s');

            if ($nuevaConsulta->save()) {
                //$relaciones = ['atencionEnfermeria', 'motivoConsulta', 'diagnosticoConsultas'];
                $relaciones = ConsultasConfiguracion::getRelaciones($modelConsulta->id_configuracion);

                foreach ($relaciones as $value) {

                    if (is_array($value)) {
                        foreach ($value as $val) {

                            if (!$this->preClonacion($modelConsulta, $val, $nuevaConsulta)) {
                                throw new Exception();
                            }
                        }
                    } else {


                        if (!$this->preClonacion($modelConsulta, $value, $nuevaConsulta)) {
                            throw new Exception();
                        }
                    }
                }
            } else {
                $nuevaConsulta->validate();

                Yii::error(sprintf("Error Nueva Consulta- %s ", $nuevaConsulta->errors));
            }

            // se modifica la consulta
            $modelConsulta->delete();

            $transaction->commit();

            return $nuevaConsulta->id_consulta;
        } catch (\Exception $ex) {

            $transaction->rollBack();
            Yii::error(sprintf("Error al clonar consulta Id %s  ", $ex->getMessage()));
            Yii::$app->session->setFlash('error', "Error al intentar editar la consulta, comuníquese con los administradores de SISSE");
        }
    }


    private function preClonacion($modelConsulta, $val, $nuevaConsulta)
    {

        $children = $modelConsulta->$val;
        $error = false;

        if (is_array($children)) { // relacion uno a muchos                            

            if (count($children) > 0) {

                foreach ($children as $child) {

                    $error = $this->clonarChilds($nuevaConsulta, $child, $val);

                    if ($error) {
                        return false;
                    }
                }
            }
        } elseif ($children != NULL) { // relacion uno a uno                            
            $error = $this->clonarChilds($nuevaConsulta, $children, $val);

            if ($error) {
                return false;
            }
        }

        return true;
    }


    private function clonarChilds($nuevaConsulta, $children, $relacion)
    {
        $child = $children;
        $newChild = clone $child;
        $newChild->{$child->getTableSchema()->primaryKey[0]} = null;
        $newChild->isNewRecord = true;
        $error = false;

        if ($relacion == "derivacionesSolicitadas") {
            $newChild->id_consulta_solicitante = $nuevaConsulta->id_consulta;
        } else {
            $newChild->id_consulta = $nuevaConsulta->id_consulta;
        }

        if (
            $relacion == "motivoConsulta"
            || $relacion == "consultaDerivaciones"
            || $relacion == "consultaPracticas"
        ) {
            $newChild->select2_codigo = ['term' => $newChild->codigo];
        }

        if ($relacion == 'personasAntecedenteConsultas' || $relacion == 'personasAntecedenteFamiliarConsultas') {
            $child->delete();
        }

        if (!$newChild->save()) {
            $newChild->validate();
            $log = sprintf("Error Nuevo hijo Único- %s - %s ", $relacion, $newChild->errors);
            Yii::error($log);
            $error = true;
        }

        return $error;
    }

    public function actionIpsHistoriaClinica($id)
    {
        /*$searchModel = new ConsultaBusqueda();
        $id_persona = Yii::$app->getRequest()->getQueryParam('id');
        $dataProvider = $searchModel->searchConsultasPersona(Yii::$app->request->queryParams,$id_persona);
        
        return $this->renderAjax('historialconsultas', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);        */
        //$json= Yii::$app->ips->getHistoriaClinica();
        //echo $json;

        return $this->renderAjax('ipshistoriaclinica');
    }

    public function actionIpsDocumentReference($id, $dominio)
    {
        return $json = Yii::$app->ips->getDocumentReference($id, $dominio);
    }

    public function actionIpsBundle($content)
    {
        $arr = explode("/", $content);
        return Yii::$app->ips->getHistoriaClinica($content);
    }

    public function actionIpsPatientLocation($id)
    {
        $mpi_api = new MpiApiController;
        $tipo = 'local'; //'mpi'
        //$id = '692869';
        //$id_mpi = 412;
        $resultado = $mpi_api->traerPaciente($id, $tipo);
        if (isset($resultado["data"]["paciente"]['set_minimo']['identificador']['mpi'])) {
            $id_mpi = $resultado["data"]["paciente"]['set_minimo']['identificador']['mpi'];
            $resultado = Yii::$app->ips->getDominios($id_mpi);
            if (!$resultado) {
                $resultado[] = array('result' => 404);
                return json_encode($resultado);
            } else {
                # code...
                return $resultado;
            }

            return;
        } else {
            $resultado[] = array('result' => 404);
            return json_encode($resultado);
        }
    }

    // el listado definitivo para todo el que realiza alguna atencion/consulta 
    // a un paciente
    public function actionListados()
    {
        $adminEfector = false;
        if (\common\models\User::hasRole('AdminEfector')) {
            $adminEfector = true;
        }
        $formularios = Yii::$app->forms->getTodosForms();

        // el formulario por defecto para el cual mostrar la informacion
        $formId = Yii::$app->request->getQueryParam('formId', $formularios[0]['id']);

        $preguntas = Yii::$app->forms->getPreguntasPorForm($formId, $adminEfector);

        $filtroModel = [];
        $filtroGridView = [];

        foreach ($preguntas as $atributos) {
            if (Yii::$app->request->getQueryParam($atributos['id'], '') != '') {
                $filtroGridView[$atributos['id']] = Yii::$app->request->getQueryParam($atributos['id']);
                $condicion = "=";
                $valor = intval(Yii::$app->request->getQueryParam($atributos['id']));

                // consideramos los casos particulares para cada form
                if ($formId == 26) {
                    // id 98 es el riesgo
                    if ($atributos['id'] == 98) {
                        if ($valor == 0) {
                            $condicion = "<";
                            $valor = 7;
                        }
                        if ($valor == 1) {
                            $condicion = "<";
                            $valor = 13;
                            $filtroModel[] = ["pregunta_id" => $atributos['id'], "condicion" => ">=", "valor" => 7];
                        }
                        if ($valor == 2) {
                            $condicion = ">";
                            $valor = 13;
                        }
                    }
                }

                $filtroModel[] = ["pregunta_id" => $atributos['id'], "condicion" => $condicion, "valor" => $valor];
            }
        }

        // cantidad total de instancias por form
        //$totalDeInstancias = Yii::$app->forms->getCantidadPorFormPorUser($formId, Yii::$app->user->id);
        /* $formsUsuario = Yii::$app->forms->getDetalleFormPorUser(Yii::$app->user->id);
        
        $totalAbsolutoDeInstancias = 0;
        foreach($formsUsuario as $formUsuario) {
            if ($formUsuario['id'] == $formId) {
                $totalAbsolutoDeInstancias = $formUsuario['cantidad'];
            }
        }*/


        $pagina = Yii::$app->request->getQueryParam('page', 1);

        if (\common\models\User::hasRole('AdminEfector')) {
            // cantidad de instancias (sin limit)
            $totalAbsolutoDeInstancias = Yii::$app->forms->getCantidadInstanciasPorEfector($formId, 4, Yii::$app->user->getIdEfector(), $filtroModel);

            // instancias

            $datosExportar = Yii::$app->forms->getInstanciasPorEfectorParaExport($formId, 4, Yii::$app->user->getIdEfector(), $filtroModel, $totalAbsolutoDeInstancias);

            $datos = Yii::$app->forms->getInstanciasPorEfector($formId, 4, Yii::$app->user->getIdEfector(), $filtroModel, (intval($pagina) * 20) - 20);

        } else {
            // cantidad de instancias (sin limit)
            $totalAbsolutoDeInstancias = Yii::$app->forms->getCantidadInstanciasPorFormPorUserEfector($formId, 4, Yii::$app->user->id, Yii::$app->user->getIdEfector(), $filtroModel);

            // instancias

            $datosExportar = Yii::$app->forms->getInstanciasPorFormPorUserEfectorParaExport($formId, 4, Yii::$app->user->id, Yii::$app->user->getIdEfector(), $filtroModel, $totalAbsolutoDeInstancias);

            $datos = Yii::$app->forms->getInstanciasPorFormPorUserEfector($formId, 4, Yii::$app->user->id, Yii::$app->user->getIdEfector(), $filtroModel, (intval($pagina) * 20) - 20);
        }



        /*echo "<pre>";
        print_r($datos["instancias"]);
        echo "</pre>";
        die;*/

        $provider = new ArrayDataProvider([
            'allModels' => $datos["instancias"],
            'totalCount' => $totalAbsolutoDeInstancias,
            'pagination' => false,/*[
                //'pageSize' => 20,
                //'page' => $pagina
            ],*/
            'sort' => [
                'attributes' => ['createdAt'],
            ],
        ]);


        $providerExportar = new ArrayDataProvider([
            'allModels' => $datosExportar["instancias"],
            'totalCount' => $totalAbsolutoDeInstancias,
            'pagination' => false,
        ]);

        $pages = new Pagination(['totalCount' => $totalAbsolutoDeInstancias, 'pageSize' => 20]);

        return $this->render('../consultas/v2/listados', array(
            'provider' => $provider,
            'providerExportar' => $providerExportar,
            'filtroGridView' => $filtroGridView,
            'totalAbsolutoDeInstancias' => $totalAbsolutoDeInstancias,
            'formularios' => $formularios,
            'formId' => $formId,
            'formTitulo' => $formularios[0]["nombre"],
            'atributos' => $preguntas,
            'pages' => $pages
        ));
    }
}
