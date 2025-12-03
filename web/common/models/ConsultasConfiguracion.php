<?php

namespace common\models;

use Yii;
use yii\helpers\Url;

use common\models\Consulta;

/**
 * This is the model class for table "consultas_configuracion".
 *
 * @property int $id
 * @property string $id_servicio
 * @property string $pasos
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string $pasos_json
 */
class ConsultasConfiguracion extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    const ENCOUNTER_CLASS_IMP = 'IMP'; //Internacion
    const ENCOUNTER_CLASS_AMB = 'AMB';
    const ENCOUNTER_CLASS_OBSENC = 'OBSENC';
    const ENCOUNTER_CLASS_EMER = 'EMER';
    const ENCOUNTER_CLASS_VR = 'VR';
    const ENCOUNTER_CLASS_HH = 'HH';

    const ENCOUNTER_CLASS = [
        'IMP' => 'Internación',
        'AMB' => 'Ambulatoria',
        'OBSENC' => 'Observación',
        'EMER' => 'Emergencia',
        'VR' => 'Virtual',
        'HH' => 'Visita Domiciliaria'
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_configuracion';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_servicio', 'encounter_class', 'pasos_json'], 'required'],
            [['id', 'id_servicio', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['encounter_class', 'pasos', 'pasos_json'], 'string'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_servicio' => 'Servicio',
            'pasos' => 'Pasos',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    }

    public static function getUrlPorServicioYEncounterClass($idServicio, $encounterClass, $paso = null)
    {        
       
        $configuracion = self::find()->where(['id_servicio' => $idServicio, 'encounter_class' => $encounterClass])
                            ->andWhere('deleted_at is null')->one();

        if (!$configuracion) {
            Yii::error("Servicio sin configuracion de pasos, servicio: ".$idServicio." encounterClass: ".$encounterClass);
            return [null, null, null, null];
            //$configuracion = self::find()->where(['id' => 1])->one();
        }
        //$arrayPasos = explode(",", $configuracion->pasos);
        $jsonPasos  = json_decode($configuracion->pasos_json);
        $arrayPasos = [];
        foreach ($jsonPasos->conf as  $output) {
            $arrayPasos[] = $output->url;
        }

        if ($paso !== null) {
            $urlAnterior = isset($arrayPasos[$paso - 1]) ? Url::toRoute(trim($arrayPasos[$paso - 1])) : null;
            $urlActual = isset($arrayPasos[$paso]) ? Url::toRoute(trim($arrayPasos[$paso])) : null;
            $urlSiguiente = isset($arrayPasos[$paso + 1]) ? Url::toRoute(trim($arrayPasos[$paso + 1])) : null;
        } else {
            $urlAnterior = null;
            $urlActual = Url::toRoute(trim($arrayPasos[0]));
            $urlSiguiente = isset($arrayPasos[1]) ? Url::toRoute(trim($arrayPasos[1])) : null;
        }

        return [$urlAnterior, $urlActual, $urlSiguiente, $configuracion->id];
    }

    public static function getRelaciones($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);

        if (!$configuracion) { return false; }
        
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        foreach ($jsonPasos->conf as  $output) {
            $arrayRelacionesPasos[] = $output->relacion;
        }
        return $arrayRelacionesPasos;
    }

    public static function checkPasoUnico($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        if (count($jsonPasos->conf) == 1) {
            return true;
        }else{
            return false;
        }        
    }

    public static function getRelacionesRequeridas($idConfiguracion)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayRelacionesPasos = [];
        foreach ($jsonPasos->conf as $output) {
            if (isset($output->requerido) && $output->requerido) {
                if (is_array($output->relacion)) {
                    foreach ($output->relacion as $relacion) {
                        $arrayRelacionesPasos[] = $relacion;
                    }
                } else {
                    $arrayRelacionesPasos[] = $output->relacion;
                }
            }
        }
        return $arrayRelacionesPasos;
    }

    /**
     * Obtener categorías con sus campos requeridos para prompts de IA
     * @param int $idConfiguracion
     * @return array
     */
    public static function getCategoriasParaPrompt($configuracion)
    {
        $jsonPasos = json_decode($configuracion->pasos_json);
        $categorias = [];

        foreach ($jsonPasos->conf as $output) {
            $categoria = [
                'titulo' => $output->titulo,
                'modelo' => $output->relacion,
                'requerido' => isset($output->requerido) ? (bool)$output->requerido : false,
                'campos_requeridos' => self::obtenerCamposRequeridosDelModelo($output->relacion)
            ];

            $categorias[] = $categoria;
        }

        return $categorias;
    }

    /**
     * Obtener campos requeridos desde las reglas de validación del modelo
     * @param string $nombreModelo
     * @return array
     */
    private static function obtenerCamposRequeridosDelModelo($nombreModelo)
    {
        $camposRequeridos = [];
        
        try {
            // Construir el nombre completo de la clase del modelo
            $claseModelo = "\\common\\models\\{$nombreModelo}";
            
            // Verificar si la clase existe
            if (!class_exists($claseModelo)) {
                return $camposRequeridos;
            }

            // Crear instancia temporal del modelo
            $modelo = new $claseModelo();
            
            // Obtener los campos requeridos desde requeridosPrompt
            $camposRequeridos = $modelo->requeridosPrompt();
            
        } catch (\Exception $e) {
            // Si hay error, devolver array vacío
            \Yii::error("Error obteniendo campos requeridos para modelo {$nombreModelo}: " . $e->getMessage());
        }
        
        return $camposRequeridos;
    }

    public static function getMenuPorIdConfiguracion($idConsulta, $idConfiguracion, $paso = null, $id_persona)
    {
        $configuracion = self::findOne($idConfiguracion);
        $jsonPasos  = json_decode($configuracion->pasos_json);

        $arrayTitulosPasos = [];
        $arrayUrlsPasos = [];
        $arrayRelaciones = [];
        $mostrarUrlsHeader= false;
        foreach ($jsonPasos->conf as $k => $output) {
            $arrayTitulosPasos[] = (isset($output->requerido) && $output->requerido)? $output->titulo.'<span style="font-size:12px;" class="text-danger"> *</span>': $output->titulo;
            $arrayUrlsPasos[] = ($idConsulta)? Url::to([$output->url.'?id_consulta='.$idConsulta.'&paso='.$k.'&id_persona='.$id_persona]): '#';
            $arrayRelaciones[] = $output->relacion;
            if(!$mostrarUrlsHeader)
                $mostrarUrlsHeader = (isset($output->requerido) && $output->requerido)? true:false;
        }
        $menu = '<nav class="nav nav-pills">';
        
        foreach ($arrayTitulosPasos as $key => $value) {
            $active = (($key == $paso) ||
                ($key == 0 && $paso == null) ||
                ($paso  == 998 &&  $key == (count($arrayTitulosPasos) - 1))) ?
                'active' : '';
            $urlAccion = ($mostrarUrlsHeader)? $arrayUrlsPasos[$key]: '#';

            $id = $arrayRelaciones[$key];

            //en relaciones, hay que contemplar la posibilidad de que venga un array
            if(is_array($arrayRelaciones[$key])){
                $id = implode('-', $arrayRelaciones[$key]);
            }

            $menu .= '<li class="nav-item " ><a id="'.$id.'" class="nav-link atender ' . $active . '" href="'.$urlAccion.'">' . $value . '</a></li>';
        }
        $menu .= '</nav>';
        if($paso === 0){
            //$menu .= '<div style="font-size: 13px;padding: 10px;" class="alert alert-danger d-flex align-items-center" role="alert">';
            //$menu .='      <div>* Para poder finalizar la consulta deberá completar los pasos obligatorios.';
            //$menu .='      </div></div>';
        }

        return $menu;
    }

    public static function getUrlPorIdConfiguracion($idConfiguracion, $paso = null)
    {
        $configuracion = self::findOne($idConfiguracion);

        //$arrayPasos = explode(",", $configuracion->pasos);
        $jsonPasos  = json_decode($configuracion->pasos_json);
        $arrayPasos = [];
        foreach ($jsonPasos->conf as  $output) {
            $arrayPasos[] = $output->url;
        }

        if ($paso !== null) {
            $urlAnterior = isset($arrayPasos[$paso - 1]) ? Url::toRoute(trim($arrayPasos[$paso - 1])) : null;
            $urlActual = isset($arrayPasos[$paso]) ? Url::toRoute(trim($arrayPasos[$paso])) : null;
            $urlSiguiente = isset($arrayPasos[$paso + 1]) ? Url::toRoute(trim($arrayPasos[$paso + 1])) : null;
            return [$urlAnterior, $urlActual, $urlSiguiente];
        }

        $urlAnterior = null;
        $urlActual = Url::toRoute(trim($arrayPasos[0]));
        $urlSiguiente = isset($arrayPasos[1]) ? Url::toRoute(trim($arrayPasos[1])) : null;

        return [$urlAnterior, $urlActual, $urlSiguiente];
    }

    public function getCreatedBy()
    {
        return $this->hasOne(User::className(), ['id' => 'created_by']);
    }

    /**
     * Este metodo verifica si se puede atender al paciente desde cierto origen (parent)
     * En caso de que la validacion sea correcta, devuelve el id de servicio correcto al cual atender
     * y el encounterClass correcto
     * 
     * @parent string
     * @parentId int
     * @paciente Persona
     */
    public static function validarPermisoAtencion($parent, $parentId, $paciente)
    {
        // Validar si el paciente está internado o en guardia
        $internacionActiva = SegNivelInternacion::personaInternada($paciente->id_persona);
        $guardiaActiva = Guardia::pacienteIngresado($paciente->id_persona);

        if ($internacionActiva || $guardiaActiva) {
            $efectorActual = Yii::$app->user->getIdEfector();
            $efectorCoincide = false;
            
            if ($internacionActiva && $internacionActiva->id_efector == $efectorActual) {
                $efectorCoincide = true;
            }
            if ($guardiaActiva && $guardiaActiva->id_efector == $efectorActual) {
                $efectorCoincide = true;
            }
            
            if (!$efectorCoincide) {
                $mensajeError = 'El paciente está en un efector diferente al seleccionado. Para cargar la consulta debe cambiar el efector desde el menú superior.';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
            }
        }
  
        if ($parent == null) {
            // TODO: Establecer un parent para una consulta espontanea. El paciente
            // se presenta espontaneamente al consultorio o a la guardia

            Yii::warning('Llamada a getModeloConsulta sin parent');
            $encounterClass = Yii::$app->user->getEncounterClass();
            $idServicio = Yii::$app->user->getServicioActual();

            $isValidEncounter = in_array($encounterClass, [
                Consulta::ENCOUNTER_CLASS_AMB, 
                Consulta::PARENT_GENERICO_EMER
            ]);
            
            if (!$isValidEncounter) {
                $mensajeError = 'El tipo de encuentro determina el tipo de consulta que se creará, por lo que es importante seleccionar el correcto antes de proceder.';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
            }
            
            $parent = ($encounterClass === Consulta::ENCOUNTER_CLASS_AMB) 
                        ? Consulta::PARENT_GENERICO_AMB 
                        : Consulta::PARENT_GENERICO_EMER;
            
            $parentId = 0;
        }
        
        // la consulta se inicia desde un turno
        if ($parent == Consulta::PARENT_TURNO) {
            $turno = Turno::findOne($parentId);

            if (!$turno) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no existe, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error con el turno, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            // verifico que el turno este en el estado correcto
            if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no pendiente, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error el turno ya fue atendido, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            $idServicio = $turno->id_servicio_asignado;
            $encounterClass = self::ENCOUNTER_CLASS_AMB;

            //$parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO];
            $parentId = $turno->id_turnos;
        }

        if ($parent == Consulta::PARENT_INTERNACION) {
            $idSegNivelInternacion = SegNivelInternacion::personaInternadaEnEfector($paciente->id_persona, Yii::$app->user->getIdEfector());

            if (!$idSegNivelInternacion) {
                Yii::warning('Llamada a getModeloConsulta parentId a un SegNivelInternacion que no existe, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error con la internacion, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = self::ENCOUNTER_CLASS_IMP;
        }

        if ($parent == Consulta::PARENT_DERIVACION) {

            $derivacion = ConsultaDerivaciones::findOne($parentId);

            if ($derivacion) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Derivacion que no existe, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error con la derivacion, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            $idServicio = $derivacion->id_servicio;
            $encounterClass = self::ENCOUNTER_CLASS_AMB;
        }

        if ($parent == Consulta::PARENT_GUARDIA) {
            $guardia = Guardia::findOne($parentId);

            if (!$guardia) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Guardia que no existe, parentId: ' . $parentId);
                $mensajeError =  'Ocurrio un error con la Guardia, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = self::ENCOUNTER_CLASS_EMER;
        }

        // Por mas que se intente una atencion generica
        // verificamos que existan Turnos, Internaciones, etc        
        if ($parent == Consulta::PARENT_GENERICO_AMB || $parent == Consulta::PARENT_GENERICO_EMER) {
            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Yii::$app->user->getEncounterClass();
            if ($encounterClass == self::ENCOUNTER_CLASS_AMB) {

                $turno = $paciente->turnoHoy($idServicio, Yii::$app->user->getIdRecursoHumano(), Yii::$app->user->getIdEfector());
                
                //Preguntar si el servicio del turno tiene pase previo de enfermeria
                if ($turno) {
                    $mensajeError = 'El paciente tiene un turno para hoy, para su servicio. Por favor busque el turno en la historia clínica y realice la atención desde dicho turno.';
                    return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                    //return Consulta::returnMsjError($mensajeError);                    
                    //$parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO];
                    //$parentId = $turno->id_turnos;
                } else {

                    //TODO: Aqui deberiamos verificar si la persona esta internada, y despues preguntar si la internacion es en el efector en sesion. Si no es asi
                    //deberiamos lanzar un error, o contemplar el caso en que la persona nunca fue dada de alta.

                    $idSegNivelInternacion = SegNivelInternacion::personaInternadaEnEfector($paciente->id_persona, Yii::$app->user->getIdEfector());

                    if ($idSegNivelInternacion) {
                        $mensajeError = 'El paciente se encuentra actualmente internado. En su historia clínica verifique los detalles de la internación y comuníquese con el personal indicado para solicitar el alta de ser necesario';
                        return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                        //return Consulta::returnMsjError($mensajeError);
                       /* $encounterClass = self::ENCOUNTER_CLASS_IMP;
                        $parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_INTERNACION];
                        $parentId = $idSegNivelInternacion;*/

                    } else {

                        $derivaciones = ConsultaDerivaciones::getDerivacionesActivasPorPacientePorServiciosPorEfector(
                            $paciente->id_persona,
                            [Yii::$app->user->getServicioActual()],
                            Yii::$app->user->getIdEfector()
                        );

                        if (count($derivaciones) > 0) {
                            $mensajeError = 'El paciente tiene un derivación para su servicio. Por favor busque el turno en la historia clínica y realice la atención desde dicho turno.';
                            return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];                            
                            //$parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_DERIVACION];
                            //$parentId = $derivaciones[0]->id;
                        } else {
                            // No posee derivaciones ni turnos, se permite el generico si el servicio
                            // en session no requiere derivacion                        
                            //$parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_GENERICO_AMB];

                            $servicio = ServiciosEfector::find()->andWhere(
                                [
                                    'id_servicio' => Yii::$app->user->getServicioActual(),
                                    'id_efector' => Yii::$app->user->getIdEfector()
                                ]
                            )->one();

                            if (!$servicio) {
                                Yii::warning('No se puede determinar el servicio del usuario, id_servicio: ' . Yii::$app->user->getServicioActual() . ', id_efector: ' . Yii::$app->user->getIdEfector());
                                $mensajeError = 'Ocurrio un error con la atencion, por favor comunicarse con los administradores de SISSE';
                                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                                //return Consulta::returnMsjError($mensajeError);
                            }

                            // No se permite atender sin derivacion
                            if (
                                $servicio->formas_atencion == ServiciosEfector::DERIVACION_DELEGAR_A_CADA_RRHH
                                || $servicio->formas_atencion == ServiciosEfector::DERIVACION_ORDEN_LLEGADA_PARA_TODOS
                            ) {
                                $mensajeError = 'Este servicio solo acepta consultas con derivación previa';
                                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                                //return Consulta::returnMsjError($mensajeError);
                            }
                        }
                    }
                }
            }
        }

        if ($parent == Consulta::PARENT_PASE_PREVIO) {

            //PRIMERO CONTROLAMOS QUE EL TURNO DE LA ATENCION CON PASE PREVIO EXISTA Y SI NO ESTA CERRADO.

            $turno = Turno::findOne($parentId);

            if (!$turno) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no existe, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error con el turno, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            // verifico que el turno este en el estado correcto
            if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no pendiente, parentId: ' . $parentId);
                $mensajeError = 'Ocurrio un error el turno ya fue atendido, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
                //return Consulta::returnMsjError($mensajeError);
            }

            //AHORA CONTROLO QUE MI SERVICO TIENE PASE PREVIO CORRESPONDIENTE AL TURNO QUE QUIERO ATENDER

            $idServicio = $turno->id_servicio_asignado; 

            $idEfector = Yii::$app->user->getIdEfector();

            $servPasePrevio = ServiciosEfector::find()
            ->where(['id_efector'=>$idEfector])
            ->andWhere(['id_servicio' => $idServicio])
            ->one();

            $idServiocioPP = $servPasePrevio->pase_previo;

            if(!isset($idServiocioPP)){
                Yii::warning('Llamada a getModeloConsulta con un servicio que no tiene pase previo');
                $mensajeError = 'Ocurrio un error, por favor comunicarse con los administradores de SISSE';
                return ['success' => false, 'msg' => $mensajeError, '$idServicio' => null, 'encounterClass' => null];
            }
            
            $idServicio = $idServiocioPP;
            $encounterClass = self::ENCOUNTER_CLASS_AMB;
            $parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_PASE_PREVIO];
            $parentId = $turno->id_turnos;
            
        }

        if ($parentId === null) {
            Yii::warning('Llamada a getModeloConsulta sin parentId');
            $mensajeError =  'Ocurrio un error, por favor comunicarse con los administradores de SISSE';
            return ['success' => false, 'msg' => $mensajeError, 'idServicio' => null, 'encounterClass' => null];
            //return Consulta::returnMsjError($mensajeError);
        }

        /*
        if ($encounterClass == self::ENCOUNTER_CLASS_EMER) {
            $parentClass = Consulta::PARENT_CLASSES[Consulta::PARENT_GENERICO_EMER];
        }
        */
        return ['success' => true, 'msg' => '', 'idServicio' => $idServicio, 'encounterClass' => $encounterClass];        
    }
}
