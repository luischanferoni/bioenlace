<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;

// Modelos
use common\models\Persona;
use common\models\Consulta;
use common\models\Turno;
use common\models\ServiciosEfector;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\SegNivelInternacion;
use common\models\Guardia;
use common\models\Alergias;
use common\models\PersonasAntecedente;
use common\models\DiagnosticoConsultaRepository as DCRepo;
use \frontend\components\UserRequest;
// Filtros y componentes
use frontend\filters\SisseActionFilter;
use webvimark\modules\UserManagement\models\User;
use yii\authclient\InvalidResponseException;

/**
 * PacienteHistorialController implementa las acciones CRUD para el modelo PacienteHistoriall.
 */
class PacienteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => SisseActionFilter::className(),
                'only' => ['historia', 'formulario-consulta', 'test'],
                'filtrosExtra' => [SisseActionFilter::FILTRO_RECURSO_HUMANO],
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
     * Obtiene el historial de un paciente específico.
     * @param integer $paciente_id
     * @return mixed
     */
    public function actionHistoria($id)
    {
        $this->layout = 'blanco';

        $paciente = Persona::findOne($id);        
        $id_turnos = Yii::$app->request->get('parent_id');

        $session = Yii::$app->getSession();
        $session->set('persona', serialize($paciente));

        $efector_sesion = Yii::$app->user->getIdEfector();
      //  var_dump(Yii::$app->user->getIdRecursoHumano());die;
        $servicios = Yii::$app->user->getServicios();

        ##consulta para turnos de un paciente#############

        //Son dos querys de turnos, la primera trae los turnos asociados especificamente al rrhh en sesion, la segunda query se encarga
        //de traer todos los turnos dados al servicio o que tienen pase previo con el servicio del rrhh en sesion. Revisar o mejorar esta query!!

        $queryTurnosDefinitiva = (new \yii\db\Query())
            ->select([
                'id' => 'turnos.id_turnos',
                'fecha' => 'CONCAT(turnos.fecha," ",turnos.hora)',
                'resumen' => 'CONCAT("Turno")',
                'parent_class' => 'turnos.parent_class',
                'id_servicio' => 'id_servicio_asignado',
                'servicio' => 'servicios.nombre',                
                'tipo' => 'turnos.estado',
                'parent_id' => 'turnos.id_turnos',
                'rr_hh' => 'CONCAT("Turno")',
                'id_rr_hh' => 'turnos.id_rrhh_servicio_asignado',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Turno")'
            ])
            ->join('LEFT JOIN', 'rrhh_servicio', 'rrhh_servicio.id = turnos.id_rrhh_servicio_asignado')
            ->join('LEFT JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = turnos.id_servicio_asignado')
            ->join('LEFT JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = turnos.id_efector')
            ->join('JOIN', 'servicios_efector as se', 'se.id_servicio = turnos.id_servicio_asignado and se.id_efector = turnos.id_efector')
            ->join('LEFT JOIN', 'servicios as pase_prev', 'pase_prev.id_servicio = se.pase_previo')
            ->from('turnos')
            ->where(['turnos.id_persona' => $id])
            ->andWhere(['turnos.id_efector' => $efector_sesion])
            ->andWhere('turnos.deleted_at IS NULL')
            ->orderBy(['fecha' => SORT_DESC]);

        $queryTurnosDefinitiva->andFilterWhere([
            'or',

            [
                'and',
                'turnos.atendido IS NULL'
            ], 

            [
                'and',
                ['turnos.atendido'=> Turno::ATENDIDO_NO],
                ['turnos.estado'=> Turno::ESTADO_SIN_ATENDER],
                ['turnos.estado_motivo'=> Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE]
            ]
        ]);

        $queryTurnosDefinitiva->andFilterWhere(
            [
                'or',
                ['rrhh_servicio.id_rr_hh' => Yii::$app->user->getIdRecursoHumano()],
                [
                    'and',
                    ['turnos.id_rrhh_servicio_asignado' => 0],
                    ['in', 'servicios.nombre', $servicios],

                ],
                [
                    'and',
                    ['in', 'pase_prev.nombre', $servicios]
                ],
            ]
            
        );

        ##consulta para Consultas de un paciente############       
        $queryConsultas = (new \yii\db\Query())
            ->select([
                'id' => 'consultas.id_consulta',
                'fecha' => 'consultas.created_at',
                'resumen' => 'CONCAT("Consulta")',
                'parent_class' => 'consultas.parent_class',
                'id_servicio' => 'consultas.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT(consultas.id_configuracion, "-", consultas.paso_completado)',
                'parent_id' => 'consultas.id_consulta',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'consultas.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Consulta")'
            ])
            //->join('JOIN', 'turnos', 'turnos.id_turnos = consultas.id_turnos OR turnos.id_turnos = consultas.parent_id') //Agregar consulta.parent_id
            //->join('JOIN', 'rrhh_servicio', 'rrhh_servicio.id = consultas.id_servicio')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = consultas.id_servicio')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = consultas.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->from('consultas')
            ->andWhere(['consultas.id_persona' => $id])
            ->andWhere(['<>', 'consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]])
            ->andWhere(['<>', 'consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION]])
            ->andWhere(['<>','consultas.parent_class', Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO]])
            ->andWhere('consultas.deleted_at IS NULL')
            ->orderBy(['fecha' => SORT_DESC]);

        ##consulta para Consultas de un paciente con parent turnos############       
        $queryConsultasTurnos = (new \yii\db\Query())
            ->select([
                'id' => 'consultas.id_consulta',
                'fecha' => 'CONCAT(turnos.fecha," ",turnos.hora)',
                'resumen' => 'CONCAT("Consulta")',
                'parent_class' => 'consultas.parent_class',
                'id_servicio' => 'consultas.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT(consultas.id_configuracion, "-", consultas.paso_completado)',
                'parent_id' => 'consultas.id_consulta',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'consultas.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Consulta")'
            ])
            ->join('JOIN', 'turnos', 'turnos.id_turnos = consultas.id_turnos OR turnos.id_turnos = consultas.parent_id') //Agregar consulta.parent_id
            //->join('JOIN', 'rrhh_servicio', 'rrhh_servicio.id = consultas.id_servicio')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = consultas.id_servicio')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = consultas.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')
            ->from('consultas')
            ->andWhere(['consultas.id_persona' => $id])
            ->andWhere(['in','consultas.parent_class', [Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO],Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO]]])
            ->andWhere('consultas.deleted_at IS NULL')
            ->orderBy(['fecha' => SORT_DESC]);

        ##seg nivel internacion#################
        $queryInternacion = (new \yii\db\Query())
            ->select([
                'id' => 'seg_nivel_internacion.id',
                'fecha' => 'TIMESTAMP(fecha_inicio,hora_inicio)',
                'resumen' => 'situacion_al_ingresar',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'tipo_ingreso',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'CONCAT("NULL")',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'seg_nivel_internacion.id_rrhh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Internacion")'
            ])
            ->join('JOIN', 'tipo_ingreso', 'seg_nivel_internacion.id_tipo_ingreso = tipo_ingreso.id_tipo_ingreso')
            ->join('JOIN', 'rrhh_servicio', 'seg_nivel_internacion.id_rrhh = rrhh_servicio.id')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'infraestructura_cama as ic', 'ic.id = seg_nivel_internacion.id_cama')
            ->join('JOIN', 'infraestructura_sala as is', 'ic.id_sala = is.id')
            ->join('JOIN', 'infraestructura_piso as ip', 'is.id_piso = ip.id')            
            ->join('JOIN', 'efectores', 'efectores.id_efector = ip.id_efector')
            ->from('seg_nivel_internacion')
            ->andWhere(['seg_nivel_internacion.id_persona' => $id])
            ->andWhere('seg_nivel_internacion.deleted_at IS NULL')
            ->orderBy(['fecha_inicio' => SORT_DESC]);

        ## guardias #################
        $queryGuardias = (new \yii\db\Query())
            ->select([
                'id' => 'guardia.id',
                'fecha' => 'TIMESTAMP(fecha,hora)',
                'resumen' => 'situacion_al_ingresar',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'situacion_al_ingresar',
                'tipo' => 'guardia.estado',
                'parent_id' => 'CONCAT("NULL")',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'guardia.id_rr_hh',
                'efector' => 'efectores.nombre',
                'tipo_historia' => 'CONCAT("Guardia")'
            ])
            ->join('JOIN', 'rrhh_efector', 'guardia.id_rr_hh = rrhh_efector.id_rr_hh')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->join('JOIN', 'efectores', 'efectores.id_efector = rrhh_efector.id_efector')            
            ->from('guardia')
            //->andWhere(['guardia.id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['guardia.id_persona' => $id])
            ->andWhere('guardia.deleted_at IS NULL')
            //->andWhere(['<>','guardia.estado',Guardia::ESTADO_FINALIZADA])
            ->orderBy(['fecha' => SORT_DESC]);

        ## documentos externos ###########
        $queryDocumentosExternos = (new \yii\db\Query())
            ->select([
                'id' => 'documentos_externos.id',
                'fecha' => 'documentos_externos.fecha',
                'resumen' => 'documentos_externos.titulo',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'rrhh_servicio.id_servicio',
                'servicio' => 'servicios.nombre',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'documentos_externos.id',
                'rr_hh' => 'CONCAT(personas.apellido," ",personas.nombre)',
                'id_rr_hh' => 'rrhh_servicio.id_rr_hh',
                'efector' => 'CONCAT("NULL")',
                'tipo_historia' => 'CONCAT("DocumentoExterno")'
            ])
            ->join('JOIN', 'rrhh_servicio', 'documentos_externos.id_rrhh_servicio = rrhh_servicio.id')
            ->join('JOIN', 'rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->join('JOIN', 'servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
            ->join('JOIN', 'personas', 'personas.id_persona = rrhh_efector.id_persona')
            ->from('documentos_externos')
            ->where(['documentos_externos.id_persona' => $id])
            //->andWhere(['documentos_externos.id_efector' => Yii::$app->user->getIdEfector()])            
            ->andWhere('documentos_externos.deleted_at IS NULL')
            ->orderBy(['fecha' => SORT_DESC]);

        ##consultas para encuestas parches mamarios de un paciente###########
        $queryEncuestasPM = (new \yii\db\Query())
            ->select([
                'id' => 'encuesta_parches_mamarios.id',
                'fecha' => 'encuesta_parches_mamarios.fecha_prueba',
                'resumen' => 'CONCAT("EncuestaParchesMamarios")',
                'parent_class' => 'CONCAT("NULL")',
                'id_servicio' => 'CONCAT("NULL")',
                'servicio' => 'CONCAT("NULL")',
                'tipo' => 'CONCAT("NULL")',
                'parent_id' => 'encuesta_parches_mamarios.id',
                'rr_hh' => 'CONCAT("NULL")',
                'id_rr_hh' => 'encuesta_parches_mamarios.id_rr_hh',
                'efector' => 'CONCAT("NULL")',
                'tipo_historia' => 'CONCAT("EncuestaParchesMamarios")'
            ])
            ->from('encuesta_parches_mamarios')
            ->andWhere(['encuesta_parches_mamarios.id_persona' => $id])
            ->andWhere('encuesta_parches_mamarios.deleted_at IS NULL')
            ->orderBy(['fecha' => SORT_DESC]);
            

        ##union de turnos consultas parches mamarios forms atencion enfermeria
        $historial = (new \yii\db\Query())
            ->from(['historial' => $queryEncuestasPM->union(
                $queryInternacion->union(
                    $queryTurnosDefinitiva->union(
                        $queryConsultas->union(
                            $queryConsultasTurnos->union(
                                $queryGuardias->union(
                                    $queryDocumentosExternos
                                )
                            )
                        )
                    )
                ))
                ])
            ->orderBy(['fecha' => SORT_DESC])->all();

        if (YII_ENV_PROD) {
            #acceso api pentalogic####################

            $jsonPentalogic = Yii::$app->imagenes->listaEstudiosPaciente($paciente->documento, null, date('Y-m-d'));
            if (isset($jsonPentalogic['result']) && !array_key_exists('message', $jsonPentalogic['result'])) {
                foreach ($jsonPentalogic['result'] as $a) {
                    $arrayPentalogic[] = array(
                        'fecha' => $a['study_datetime'],
                        'resumen' => $a['url'],
                        'parent_class' => 'Estudios Imagenes',
                        'id_servicio' => null,
                        'servicio' => null,
                        'tipo' => $a['study_desc'],
                        'parent_id' => $a['accession_no'], // pongo aqui este valor para poder mostrar el informe en la vista
                        'rr_hh' => null,
                        'efector' => null,
                        'tipo_historia' => 'EstudiosImagenes'
                    );

                }
                $historial = array_merge($historial, $arrayPentalogic);
            }
            ###########acceso a Forms #######################
            try{
                $arrayForms = $this->getForms($id);
            } catch (\Throwable $e) {
                $arrayForms = null;
            }

            if($arrayForms) {
                $historial = array_merge($historial, $arrayForms);
            }

            ####################################################
        
            // ###########acceso a Sianlabs #######################
           /* $client = Yii::$app->authClientCollection->getClient('sianlabs');

            // direct authentication of client only:
            $accessToken = $client->authenticateClient();*/
            #####encontrar id Patient###########################
            #$patient = Yii::$app->sianlabs->getIdPatient('29384314',$accessToken->getToken());

            try {
                $client = Yii::$app->authClientCollection->getClient('sianlabs');                
                $accessToken = $client->authenticateClient();
                
            } catch (InvalidResponseException $e) {
                $accessToken = null;
            } catch (\Throwable $e) {
                $accessToken = null;
            }

            if ($accessToken) {

            $idpatient = null;
           // echo $accessToken->getToken(); die;
            $patient = Yii::$app->sianlabs->getIdPatient($paciente->documento, $accessToken->getToken())??[];

            foreach ($patient as $k => $le) :
                if ($k == 'entry') :
                    foreach ($le as $f => $te) :
                        foreach ($te as $key => $t) :
                            if ($key == 'resource') :
                                foreach ($t as $ke => $id) :
                                    if ($ke == 'id') :
                                        $idpatient = $id;
                                    endif;
                                endforeach;
                            endif;
                        endforeach;
                    endforeach;
                endif;
            endforeach;
            #####encontar informes#############################
            if ($idpatient) :
                $lab = Yii::$app->sianlabs->getDiagnosticReport($idpatient, $accessToken->getToken())??[];                
                $arraySianlab = null;
                $modal = 0;
                foreach ($lab as $k => $le) :
                    if ($k == 'meta') :
                        $fecha = $le['lastUpdated'];
                    endif;
                    if ($k == 'entry') :
                        foreach ($le as $f => $te) :
                            foreach ($te as $key => $t) :
                                if ($key == 'resource') :
                                    foreach ($t as $ke => $text) :
                                        if ($ke == 'text') :
                                            $modal++;
                                            $arraySianlab[$modal] = array(
                                                'fecha' => null,
                                                'resumen' => trim($text['div']),
                                                'parent_class' => 'EstudiosLab',
                                                'servicio' => null,
                                                'tipo' => null,
                                                'parent_id' => $modal,
                                                'rr_hh' => null,
                                                'efector' => null,
                                                'tipo_historia' => 'EstudiosLab'
                                            );
                                        endif;
                                        if ($ke == 'effectiveDateTime') :
                                            $arraySianlab[$modal]['fecha'] = $text;
                                        endif;
                                        if ($ke == 'performer') :
                                            foreach ($text as $keyType => $type) :
                                                if ($type['type'] == 'Organization') $arraySianlab[$modal]['efector'] = trim($type['display']);
                                                if ($type['type'] == 'Practitioner') $arraySianlab[$modal]['rr_hh'] = $type['display'];
                                            endforeach;
                                        endif;
                                    endforeach;
                                endif;
                            endforeach;
                        endforeach;
                    endif;
                endforeach;
                if ($arraySianlab) :
                    $historial = array_merge($historial, $arraySianlab);
                endif;
            endif;
            }
            ##################################################
        }

        usort($historial, function ($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        list($condicionesActivas, $condicionesCronicas) =
                DCRepo::getCondicionesPaciente($paciente->id_persona);

        // Datos simulados para diagnósticos recientes (solo en dev o si se solicita)
        if ((defined('YII_DEBUG') && YII_DEBUG) || Yii::$app->getRequest()->getQueryParam('simular_diagnosticos')) {
            if (empty($condicionesActivas)) {
                $condicionesActivas = [];
                $diag1 = new \stdClass();
                $diag1->codigoSnomed = (object)['term' => 'Hipertensión arterial'];
                $diag2 = new \stdClass();
                $diag2->codigoSnomed = (object)['term' => 'Diabetes mellitus tipo 2'];
                $condicionesActivas[] = $diag1;
                $condicionesActivas[] = $diag2;
            }
        }
        $hallazgos = \common\models\Alergias::find()->where(['id_persona' => $paciente->id_persona])->all();
        $antecedentes_personales = \common\models\PersonasAntecedente::find()->where(['id_persona' => $paciente->id_persona, 'tipo_antecedente' => 'Personal'])->all();
        $antecedentes_familiares = \common\models\PersonasAntecedente::find()->where(['id_persona' => $paciente->id_persona, 'tipo_antecedente' => 'Familiar'])->all();
        
        // Simulación de datos adicionales
        if ((defined('YII_DEBUG') && YII_DEBUG) || Yii::$app->getRequest()->getQueryParam('simular_diagnosticos')) {
            // Simular diagnósticos crónicos
            if (empty($condicionesCronicas)) {
                $condicionesCronicas = [];
                $cronico1 = new \stdClass();
                $cronico1->codigoSnomed = (object)['term' => 'Hipertensión arterial esencial'];
                $cronico2 = new \stdClass();
                $cronico2->codigoSnomed = (object)['term' => 'Diabetes mellitus tipo 2'];
                $cronico3 = new \stdClass();
                $cronico3->codigoSnomed = (object)['term' => 'Artritis reumatoide'];
                $condicionesCronicas[] = $cronico1;
                $condicionesCronicas[] = $cronico2;
                $condicionesCronicas[] = $cronico3;
            }
            
            // Simular alergias
            if (empty($hallazgos)) {
                $hallazgos = [];
                $alergia1 = new \stdClass();
                $alergia1->codigoSnomed = (object)['term' => 'Alergia a penicilina'];
                $alergia2 = new \stdClass();
                $alergia2->codigoSnomed = (object)['term' => 'Alergia a polen'];
                $alergia3 = new \stdClass();
                $alergia3->codigoSnomed = (object)['term' => 'Alergia a mariscos'];
                $hallazgos[] = $alergia1;
                $hallazgos[] = $alergia2;
                $hallazgos[] = $alergia3;
            }
            
            // Simular antecedentes personales
            if (empty($antecedentes_personales)) {
                $antecedentes_personales = [];
                $ant1 = new \stdClass();
                $ant1->snomedSituacion = (object)['term' => 'Cirugía de apendicectomía'];
                $ant2 = new \stdClass();
                $ant2->snomedSituacion = (object)['term' => 'Fractura de brazo derecho'];
                $antecedentes_personales[] = $ant1;
                $antecedentes_personales[] = $ant2;
            }
            
            // Simular antecedentes familiares
            if (empty($antecedentes_familiares)) {
                $antecedentes_familiares = [];
                $antFam1 = new \stdClass();
                $antFam1->snomedSituacion = (object)['term' => 'Diabetes mellitus en padre'];
                $antFam2 = new \stdClass();
                $antFam2->snomedSituacion = (object)['term' => 'Hipertensión arterial en madre'];
                $antecedentes_familiares[] = $antFam1;
                $antecedentes_familiares[] = $antFam2;
            }
        }

        $ultimaAtencionEnfermaria = \common\models\ConsultaAtencionesEnfermeria::ultimoPorPaciente($paciente->id_persona);

        /*$internacionActiva = false;
        if ($historial[0]["parent_class"] === Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION] 
            || $historial[0]["tipo_historia"] === "Internacion") {
                $internacionActiva = true;
        }*/
        
        // Si llega este parametro, lanza el modal de consulta automaticamente
      /*  $atender = Yii::$app->request->get('atender');
        $atender = $atender == "" || $atender == null ? false : true;

        // Para agregar datos de la tabla ConsultaDerivaciones a la consulta
        $consultaPS = false;
        $referencia = ['tipo_solicitud' => null, 'dato' => null];

        if ($id_turnos) {
            $turno = Turno::find()->where(['id_turnos' => $id_turnos])
                              ->one();
            $consultaPS = ConsultaDerivaciones::getPracticaSolicitadasPorIdConsultaSolicitada($turno->id_consulta_referencia);

            if($consultaPS):
                if($consultaPS->tipo_solicitud == 'INTERCONSULTA'):
                    $referencia = ['tipo_solicitud' => 'INTERCONSULTA', 'dato' => $consultaPS->indicaciones];
                else:
                    $referencia = ['tipo_solicitud' => 'PRACTICA', 'dato' => $consultaPS->codigo];
                endif;
            endif;            
        }        
        */

        //Traigo pase previo del servicio actual desde UserConfig (Yii::$app->user)
        $idServicio = Yii::$app->user->getServicioActual();
        $idEfector = Yii::$app->user->getIdEfector();
        $encounterClass = Yii::$app->user->getEncounterClass();

        $servPasePrevio = ServiciosEfector::find()
            ->where(['id_efector'=>$idEfector])
            ->andWhere(['id_servicio' => $idServicio])
            ->one();

        $idServicioPP = !is_null($servPasePrevio) ? $servPasePrevio->pase_previo : 0;

        //TODO: hacer un ordenamiento del historial completo, tal vez ahaya que transformar las fechas para ordenar y volverlas a poner con formato al mostrarlas
        // echo "<pre>";
        // var_dump($historial);
        // echo "</pre>";

        // var_dump(ArrayHelper::multisort($historial, ['fecha'], [SORT_DESC]));die;

        // fin del calculo

        return $this->render('timeline/timeline', [
            'persona' => $paciente,
            'historial' => $historial,
            'condicionesActivas' => $condicionesActivas,
            'condicionesCronicas' => $condicionesCronicas,
            'hallazgos' => $hallazgos,
            'antecedentes_personales' => $antecedentes_personales,
            'antecedentes_familiares' => $antecedentes_familiares,
            'ultimaAtencionEnfermaria' => $ultimaAtencionEnfermaria,   
            'pase_previo' =>  $idServicioPP,

            'encounterClass' => $encounterClass,
            'servicioActual' => $idServicio
        ]);
    }

    /**
     * Endpoint AJAX para obtener el estado del formulario y mensajes.
     * @param integer $id ID del paciente
     * @return Response JSON con HTML del formulario y mensajes
     */
    public function actionFormularioConsulta($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        try {
            $paciente = $this->findModel($id);
            
            $idConsulta = Yii::$app->request->get('id_consulta');
            $parentId = Yii::$app->request->get('parent_id');
            $parent = Yii::$app->request->get('parent');

            // La validación de internación/guardia ahora se hace en validarPermisoAtencion
            $mostrarFormulario = true;
            $mensajeCondicion = '';
            $mensajeCambioEfector = '';

            // Obtener configuración de pasos para el formulario
            $resultadoConfiguracion = $this->obtenerConfiguracion($idConsulta, $paciente, $parent, $parentId);

            if (!$resultadoConfiguracion['success']) {
                    $mensajeCondicion = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> 
                            <strong>Error:</strong>
                            <small class="text-muted">'.$resultadoConfiguracion['msg'].'</small></div>';
                    $mostrarFormulario = false;
            }

            // Generar HTML del formulario
            $formularioHtml = '';
            if ($mostrarFormulario) {
                $idConfiguracion = $resultadoConfiguracion['idConfiguracion'] ?? null;
                $formularioHtml = $this->renderPartial('_formulario_consulta', [
                    'paciente' => $paciente,
                    'idConfiguracion' => $idConfiguracion
                ]);
            }

            $response = [
                'success' => true,
                'mostrarFormulario' => $mostrarFormulario,
                'formularioHtml' => $formularioHtml,
                'mensajeCondicion' => $mensajeCondicion,
                'mensajeCambioEfector' => $mensajeCambioEfector                
            ];

            // Agregar id_configuracion si el formulario se muestra
            if ($mostrarFormulario && isset($resultadoConfiguracion['idConfiguracion'])) {
                $response['id_configuracion'] = $resultadoConfiguracion['idConfiguracion'];
            }

            return $response;
            
        } catch (\Exception $e) {
            Yii::error('Error en actionFormularioConsulta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener el estado del formulario'
            ];
        }
    }

    public function getForms($id_persona)
    {
        $respuesta = false;
        try {
            $client = new \yii\httpclient\Client();
            $request = str_replace('"#PERSONAID"', $id_persona, file_get_contents('../assets/jsonForms/formsPorPersonaId.json'));
            $decodedText = urlencode($request);

            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl(Yii::$app->params['hostFormsAPI'] . '/instancias?filter=' . $decodedText)
                ->setHeaders(['Content-type' => 'application/json'])
                ->setData([])
                ->send();

                //var_dump($response->data);
            if (!$response->isOk) {
                $respuesta = false;
            } else {
                //var_dump($response->data);
                foreach ($response->data as $instancia) {

                    $respuesta[] = array(
                        'fecha' => $instancia['createdAt'],
                        'resumen' => 'form/verinstancia/' . $instancia['id'],
                        'parent_class' => 'Forms',
                        'servicio' => null,
                        'tipo' => null,
                        'parent_id' => $instancia['form']['nombre'],
                        'rr_hh' => null,
                        'efector' => null,
                        'tipo_historia' => 'Forms'
                    );
                }
            }
        } catch (\Exception $e) {
            return $respuesta;
        }

        return $respuesta;
    }

    /**
     * Busca un registro de Persona basado en su valor de clave primaria.
     * Si el modelo no se encuentra, se lanzará una excepción HTTP 404.
     * @param integer $id
     * @return Persona el modelo cargado
     * @throws NotFoundHttpException si el modelo no se encuentra
     */
    protected function findModel($id)
    {
        if (($model = Persona::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La página solicitada no existe.');
    }

    /**
     * Obtiene la configuración de pasos para el formulario de consulta
     * @param integer|null $idConsulta ID de la consulta existente
     * @param Persona $paciente Paciente
     * @param string $parent Tipo de parent
     * @param integer $parentId ID del parent
     * @return array Configuración de pasos
     */
    private function obtenerConfiguracion($idConsulta, $paciente, $parent, $parentId)
    {
        if ($idConsulta) {
            $modelConsulta = Consulta::findOne($idConsulta);
            if ($modelConsulta) {
                $configuracion = \common\models\ConsultasConfiguracion::findOne($modelConsulta->id_configuracion);
            }
        } else {
            // Determinar configuración basada en el servicio y encounter class
            $resultadoValidacion = \common\models\ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
            if (!$resultadoValidacion['success']) {
                return $resultadoValidacion;
            }

            $configuracion = \common\models\ConsultasConfiguracion::find()
                ->where(['id_servicio' => $resultadoValidacion['idServicio']])
                ->andWhere(['encounter_class' => $resultadoValidacion['encounterClass']])
                ->andWhere('deleted_at is null')
                ->one();
        }

        if (!$configuracion) {
            return ['success' => false, 'msg' => 'Error: Servicio sin configuración'];
        }

        return ['success' => true, 'msg' => '', 'idConfiguracion' => $configuracion->id];
    }

}
