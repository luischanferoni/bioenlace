<?php

namespace common\models;

use Yii;
use yii\helpers\Url;
use common\models\Persona;
use common\traits\ParameterQuestionsTrait;



/**
 * This is the model class for table "turnos".
 *
 * @property string $id_turnos
 * @property integer $id_persona
 * @property string $fech
 * @property string $hora
 * @property string $id_rr_hh
 * @property string $confirmado
 * @property string $referenciado
 * @property integer $id_consulta_referencia
 * @property string $id_servicio
 * @property string $usuario_alta
 * @property string $fecha_alta
 * @property string $usuario_mod
 * @property string $fecha_mod
 * 
 * @chatbot-category turnos
 * @chatbot-category-name "Gestión de Turnos"
 * @chatbot-category-description "Acciones concretas relacionadas con turnos médicos"
 * 
 * @chatbot-intent crear_turno
 * @chatbot-intent-name "Crear Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "sacar turno,reservar turno,agendar turno,pedir turno,necesito turno,quiero turno,turno para,turno con,agendar,reservar,sacar cita,cita médica"
 * @chatbot-intent-patterns "/\b(sacar|reservar|agendar|pedir|necesito|quiero)\s+(un\s+)?turno/i,/turno\s+(para|con|de)/i,/agendar\s+(cita|consulta)/i"
 * @chatbot-intent-required-params servicio,fecha,hora
 * @chatbot-intent-optional-params profesional,efector,observaciones
 * @chatbot-intent-lifetime 600
 * @chatbot-intent-patient-profile-can-use professional,efector,service
 * @chatbot-intent-patient-profile-resolve-references true
 * @chatbot-intent-patient-profile-update-on-complete-type professional
 * @chatbot-intent-patient-profile-update-on-complete-fields id_rr_hh,id_efector,servicio
 * @chatbot-intent-patient-profile-cache-ttl 3600
 * 
 * @chatbot-intent modificar_turno
 * @chatbot-intent-name "Modificar Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "cambiar turno,modificar turno,reagendar turno,cambiar fecha,cambiar horario,mover turno,reagendar,modificar cita"
 * @chatbot-intent-patterns "/\b(cambiar|modificar|reagendar|mover)\s+(el\s+)?turno/i,/cambiar\s+(fecha|horario|hora)/i"
 * @chatbot-intent-required-params turno_id
 * @chatbot-intent-optional-params fecha,hora,profesional
 * @chatbot-intent-lifetime 600
 * @chatbot-intent-patient-profile-can-use professional
 * @chatbot-intent-patient-profile-resolve-references false
 * @chatbot-intent-patient-profile-cache-ttl 3600
 * 
 * @chatbot-intent cancelar_turno
 * @chatbot-intent-name "Cancelar Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "cancelar turno,anular turno,borrar turno,no puedo ir,no voy a ir,cancelar cita"
 * @chatbot-intent-patterns "/\b(cancelar|anular|borrar)\s+(el\s+)?turno/i,/no\s+(puedo|voy)\s+a\s+ir/i"
 * @chatbot-intent-required-params turno_id
 * @chatbot-intent-optional-params
 * @chatbot-intent-lifetime 300
 * @chatbot-intent-patient-profile-can-use
 * @chatbot-intent-patient-profile-resolve-references false
 * 
 * @chatbot-intent consultar_turnos
 * @chatbot-intent-name "Consultar Turnos"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority medium
 * @chatbot-intent-keywords "mis turnos,ver turnos,turnos futuros,próximo turno,cuándo es mi turno,qué turnos tengo,turnos pasados"
 * @chatbot-intent-patterns "/\b(mis|ver|consultar)\s+turnos/i,/pr[oó]ximo\s+turno/i,/cu[áa]ndo\s+es\s+mi\s+turno/i"
 * @chatbot-intent-required-params
 * @chatbot-intent-optional-params fecha_desde,fecha_hasta,servicio
 * @chatbot-intent-lifetime 300
 * @chatbot-intent-patient-profile-can-use
 * @chatbot-intent-patient-profile-resolve-references false
 * 
 * @chatbot-intent disponibilidad_turnos
 * @chatbot-intent-name "Disponibilidad de Turnos"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority medium
 * @chatbot-intent-keywords "horarios disponibles,disponibilidad,turnos disponibles,qué horarios hay,cuándo hay turno"
 * @chatbot-intent-patterns "/horarios?\s+disponibles/i,/turnos?\s+disponibles/i,/cu[áa]ndo\s+hay\s+turno/i"
 * @chatbot-intent-required-params
 * @chatbot-intent-optional-params servicio
 * @chatbot-intent-lifetime 300
 * @chatbot-intent-patient-profile-can-use
 * @chatbot-intent-patient-profile-resolve-references false
 */
class Turno extends \yii\db\ActiveRecord
{
    use ParameterQuestionsTrait;
    use \common\traits\SoftDeleteDateTimeTrait;

    public $cant_turnos;

    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_CANCELADO = 'CANCELADO';
    const ESTADO_EN_ATENCION = 'EN_ATENCION';
    const ESTADO_ATENDIDO = 'ATENDIDO';
    const ESTADO_SIN_ATENDER = 'SIN_ATENDER';

    const ESTADO_MOTIVO_ERROR_CARGA = 'ERROR_CARGA';
    const ESTADO_MOTIVO_CANCELADO_PACIENTE = 'CANCELADO_X_PACIENTE';
    const ESTADO_MOTIVO_CANCELADO_MEDICO = 'CANCELADO_X_MEDICO';
    const ESTADO_MOTIVO_SIN_ATENDER_PACIENTE = 'SIN_ATENDER_X_PACIENTE';
    const ESTADO_MOTIVO_SIN_ATENDER_MEDICO = 'SIN_ATENDER_X_MEDICO';

    const TIPO_ATENCION_PRESENCIAL = 'presencial';
    const TIPO_ATENCION_TELECONSULTA = 'teleconsulta';

    //Esta constante considera los estados de los turnos que me deshabilitan slots en el calendario.
    const ESTADOS_PARA_DESHABILITAR = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_ATENDIDO,
        self::ESTADO_EN_ATENCION,

    ];

    const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_CANCELADO => 'Cancelado',
        self::ESTADO_EN_ATENCION => 'En Atencion',
        self::ESTADO_ATENDIDO => 'Atendido',
        self::ESTADO_SIN_ATENDER => 'Sin atender'
    ];
    // Estado Motivos: key => Descripcion
    const ESTADO_MOTIVO = [
        self::ESTADO_MOTIVO_ERROR_CARGA => 'ERROR DE CARGA',
        self::ESTADO_MOTIVO_CANCELADO_PACIENTE => 'CANCELADO POR PACIENTE',
        self::ESTADO_MOTIVO_CANCELADO_MEDICO => 'CANCELADO POR MEDICO',
        self::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE => 'SIN ATENDER POR PACIENTE',
        self::ESTADO_MOTIVO_SIN_ATENDER_MEDICO => 'SIN ATENDER POR MEDICO'
    ];
    // las siguientes van a ser reemplazadas por ESTADOS
    const ATENDIDO_SI = 'SI';
    const ATENDIDO_NO = 'NO';
    const ATENDIDO_EN_ATENCION = 'EN ATENCION';

    public static function getMotivosCancelacion()
    {
        $motivos_cancel = [self::ESTADO_MOTIVO_ERROR_CARGA, self::ESTADO_MOTIVO_CANCELADO_PACIENTE,  self::ESTADO_MOTIVO_CANCELADO_MEDICO];
        $motivos = [];
        foreach ($motivos_cancel as $key) {
            $motivos[$key] = Turno::ESTADO_MOTIVO[$key];
        }
        return $motivos;
    }
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'turnos';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => isset(Yii::$app->user->id) ? Yii::$app->user->id : 1,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'hora', 'fecha'], 'required'],
            [['id_rrhh_servicio_asignado'], 'required', 'on' => ServiciosEfector::DELEGAR_A_CADA_RRHH],
            [['id_servicio_asignado'], 'required', 'on' => ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS],
            [['id_persona', 'id_rr_hh', 'id_consulta_referencia', 'id_servicio_asignado', 'id_servicio', 'id_rrhh_servicio_asignado', 'programado'], 'integer'],
            // no deja crear un turno para la misma persona para el mismo recurso en el mismo dia
            [
                ['fecha', 'id_persona', 'id_rrhh_servicio_asignado'], 'unique',
                'targetAttribute' => ['fecha', 'id_persona', 'id_rrhh_servicio_asignado'],
                'filter' => function ($query) {
                    $query->andWhere(['estado' => 'PENDIENTE']);
                },
                'message' => 'Ya existe un turno para este paciente para este médico para la fecha indicada',
                'on' => ServiciosEfector::DELEGAR_A_CADA_RRHH,
            ],
            [
                ['fecha', 'id_persona', 'id_servicio_asignado'], 'unique',
                'targetAttribute' => ['fecha', 'id_persona', 'id_servicio_asignado'],
                'filter' => function ($query) {
                    $query->andWhere(['estado' => 'PENDIENTE']);
                },
                'message' => 'Ya existe un turno para este paciente para este servicio para la fecha indicada',
                'on' => ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS,
            ],
            [['fecha', 'hora', 'fecha_alta', 'fecha_mod'], 'safe'],
            [['confirmado', 'referenciado'], 'string'],
            [['usuario_alta', 'usuario_mod'], 'string', 'max' => 40],
            ['hora', 'compare', 'compareValue' => date("H:i"), 'operator' => '>', 'when' => function ($model) {
                if ($model->fecha == date('Y-m-d')) {
                    return true;
                } else {
                    return false;
                }
            }],
            [['id_rrhh_servicio_asignado'], 'default', 'value' => 0],
            [['tipo_atencion'], 'string', 'max' => 20],
            [['tipo_atencion'], 'in', 'range' => [self::TIPO_ATENCION_PRESENCIAL, self::TIPO_ATENCION_TELECONSULTA]],
            [['tipo_atencion'], 'default', 'value' => self::TIPO_ATENCION_PRESENCIAL],
            ['estado_motivo', 'in', 'range' => array_keys(Turno::ESTADO_MOTIVO)],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_turnos' => 'Id Turnos',
            'id_persona' => 'Paciente',
            'fecha' => 'Fecha del Turno',
            'hora' => 'Hora del Turno',
            'id_rr_hh' => 'Profesional',
            'confirmado' => 'Confirmado',
            'referenciado' => 'Referenciado',
            'id_consulta_referencia' => 'Efector de Referencia',
            'id_servicio_asignado' => 'Servicio',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
            'usuario_mod' => 'Usuario Mod',
            'fecha_mod' => 'Fecha Mod',
            'tipo_atencion' => 'Tipo de atención',
        ];
    }
    
    /**
     * Preguntas para parámetros del chatbot
     * @return array
     */
    public function parameterQuestions()
    {
        return [
            'fecha' => '¿Para qué día querés el turno?',
            'hora' => '¿En qué horario te gustaría?',
            'horario' => '¿En qué horario te gustaría?',
            'turno_id' => '¿Qué turno querés modificar/cancelar?',
            'id_turnos' => '¿Qué turno querés modificar/cancelar?',
        ];
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio_asignado']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }
    public function getRrhh()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getRrhhServicioAsignado()
    {
        return $this->hasOne(RrhhServicio::className(), ['id' => 'id_rrhh_servicio_asignado']);
    }

    public function getAgenda_rrhh()
    {
        return $this->hasMany(Agenda_rrhh::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getUserAlta()
    {
        return $this->hasOne(Persona::className(), ['id_user' => 'usuario_alta']);
    }

    public function getUserModificacion()
    {
        return $this->hasOne(Persona::className(), ['id_user' => 'usuario_mod']);
    }

    public static function getTurnosPorRrhhPorFecha($fecha, $idRrhh)
    {
        $rrhh = RrhhEfector::findOne($idRrhh);
        $idsRrhhServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id');
        $idsServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id_servicio');


        $t = RrhhEfector::obtenerServicioActual();

        // Traigo los servicios que podrian requerir pasar por el servicio actual del rrhh
        $serviciosConPasePrevio = ServiciosEfector::find()
            ->andWhere(['servicios_efector.id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['in', 'servicios_efector.pase_previo', $idsServicios])
            ->all();

        $idServiciosConPasePrevio = \Yii\helpers\ArrayHelper::getColumn($serviciosConPasePrevio, 'id_servicio');

        $totalIdsServicios = array_unique(array_merge($idsServicios, $idServiciosConPasePrevio));
        //echo $serviciosConPasePrevio->createCommand()->getRawSql();die;
        // En turnos se guarda id_rrhh_servicio_asignado o id_servicio, si se trata de un turno asignado al rrhh especifico
        // o al servicio en el cual el rrhh esta trabajando en este momento
        $query = Turno::findActive()->where(['id_efector' => Yii::$app->user->getIdEfector()]);

        // los servicios que poseen pase previo con id igual al servicio actual en session -> $t['id_servicio']
        $query->andFilterWhere(
            [
                'or',
                ['in', 'id_rrhh_servicio_asignado', $idsRrhhServicios],
                [
                    'and',
                    ['id_rrhh_servicio_asignado' => 0],
                    ['in', 'id_servicio_asignado', $totalIdsServicios],

                ],
                [
                    'and',
                    ['in', 'id_servicio_asignado', $idServiciosConPasePrevio]
                ],
            ]


        );

        $turnos = $query->andWhere(['fecha' => $fecha])
            ->andWhere(['estado' => 'PENDIENTE'])
            ->andWhere(['is', 'atendido', NULL])
            ->orderBy('hora')
            //echo $query->createCommand()->getRawSql();die;
            ->all();
        // SELECT * FROM `turnos` WHERE ((`id_rrhh_servicio_asignado`=1273) OR ((`id_servicio_asignado`=6) AND (`id_efector`='786'))) AND (`fecha`='2023-09-07') AND (turnos.atendido IS NULL) ORDER BY `hora`
        return $turnos;
    }

    public static function getAllTurnosPorRrhhPorFecha($fecha, $idRrhh)
    {
        $rrhh = RrhhEfector::findOne($idRrhh);
        $idsRrhhServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id');
        $idsServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id_servicio');

        $t = RrhhEfector::obtenerServicioActual();

        // Traigo los servicios que podrian requerir pasar por el servicio actual del rrhh
        $serviciosConPasePrevio = ServiciosEfector::find()
            ->andWhere(['servicios_efector.id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['in', 'servicios_efector.pase_previo', $idsServicios])
            ->all();
        $idServiciosConPasePrevio = \Yii\helpers\ArrayHelper::getColumn($serviciosConPasePrevio, 'id_servicio');

        $totalIdsServicios = array_unique(array_merge($idsServicios, $idServiciosConPasePrevio));
        //echo $serviciosConPasePrevio->createCommand()->getRawSql();die;
        // En turnos se guarda id_rrhh_servicio_asignado o id_servicio, si se trata de un turno asignado al rrhh especifico
        // o al servicio en el cual el rrhh esta trabajando en este momento
        $query = Turno::findActive();

        // los servicios que poseen pase previo con id igual al servicio actual en session -> $t['id_servicio']
        $query->andFilterWhere(
            [
                'or',
                ['in', 'id_rrhh_servicio_asignado', $idsRrhhServicios],
                [
                    'and',
                    ['id_rrhh_servicio_asignado' => 0],
                    ['id_servicio_asignado' => $totalIdsServicios],
                    ['id_efector' => Yii::$app->user->getIdEfector()],
                ],
            ]
        );

        $turnos = $query->andWhere(['fecha' => $fecha])
            #->andWhere(['estado' => 'PENDIENTE'])            
            ->orderBy('hora')
            //echo $query->createCommand()->getRawSql();die;
            ->all();
        // SELECT * FROM `turnos` WHERE ((`id_rrhh_servicio_asignado`=1273) OR ((`id_servicio_asignado`=6) AND (`id_efector`='786'))) AND (`fecha`='2023-09-07') AND (turnos.atendido IS NULL) ORDER BY `hora`
        return $turnos;
    }

    public function formatearFecha($date)
    {
        list($d, $m, $y) = explode("-", $date);
        return "$y-$m-$d";
    }


    public static function NoSePresento($id_turno)
    {
        $connection = new \yii\db\Query;
        $connection->createCommand()
            ->update(
                'turnos',
                [
                    'atendido' => 'NO',
                    'estado' => 'SIN_ATENDER',
                    'estado_motivo' => 'SIN_ATENDER_X_PACIENTE'
                ],
                'id_turnos = ' . $id_turno
            )
            ->execute();
    }

    public static function cambiarCampoAtendido($id_turnos, $estado)
    {

        Yii::$app->db->createCommand()
            ->update(
                'turnos',
                [
                    'atendido' => $estado,
                    'estado' => 'ATENDIDO'
                ],
                'id_turnos = ' . $id_turnos
            )
            ->execute();
    }

    public static function cargarRrhhServicioAsignado($id_turnos, $id_servicio_asignado)
    {
        $id_rrhh_servicio_asignado = RrhhServicio::obtenerIdRrhhServicio(Yii::$app->user->getIdRecursoHumano(), $id_servicio_asignado);

        Yii::$app->db->createCommand()
            ->update('turnos', ['id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado], 'id_turnos = ' . $id_turnos)
            ->execute();
    }

    public static function cantidadDePacientesxDia()
    {
        $fecha = '2023-11-21';

        $cantTurnos = self::find()
            ->where(['fecha' => $fecha])
            ->all();

        return count($cantTurnos);
    }

    public static function estadisticasDiariasPorRrhh()
    {

        $fecha = date("Y-m-d");
        $rrhh = Yii::$app->user->getIdRecursoHumano();
        $efector = Yii::$app->user->getIdEfector();
        $servicio = Yii::$app->user->getServicioActual();
        $cantAtendidos = 0;
        $cantNoAtendidos = 0;
        $cantTurnos = 0;

        //Traer todos los turnos dados al servicio de la persona en sesion

        $turnos = self::find()
            ->where([['fecha' => $fecha]])
            ->andWhere(['id_efector' => $efector])
            ->andWhere(['id_servicio_asignado' => $servicio])
            ->all();

        //Calculamos los turnos atendidos y los no atendidos

        foreach ($turnos as $turno) {

            if ($turno->atendido == 'SI') {
                $cantAtendidos++;
            } else {
                $cantNoAtendidos++;
            }
        }

        $rrhh_efector = RrhhEfector::find()
            ->where(['id_rr_hh' => $rrhh])
            ->andWhere(['id_efector' => $efector])
            ->one();

        $rrhh_servicio = $rrhh_efector->rrhhServicio->id_rr_hh;

        //calculamos los turnos atendidos por el profesional                
        $turnosAtendidos = self::find()
            ->where([['fecha' => $fecha]])
            ->andWhere(['id_efector' => $efector])
            ->andWhere(['id_rrhh_servicio_asignado' => $rrhh_efector])
            ->andWhere(['atendido' => 'SI'])
            ->all();

        return [count($turnos), $cantAtendidos, $cantNoAtendidos, count($turnosAtendidos)];
    }

    public static function pacienteEsperandoTurno($id_persona)
    {
        $turnos = self::find()
            ->where(['id_persona' => $id_persona])
            ->andWhere(['fecha' => date("Y-m-d")])
            ->andWhere(['estado' => self::ESTADO_PENDIENTE])
            ->all();


        return $turnos;
    }

    public static function footerTimeline($tipo, $id, $idServicioAsignado = '', $pase_previo = '', $id_persona = '', $fecha)
    {

        $footer = "";
        switch ($tipo) {
            case self::ESTADO_PENDIENTE:
                // SisseGhostHtml::a

                $idServicioSesion = Yii::$app->user->getServicioActual();
                $idServicioTurno = $idServicioAsignado;
                $ocultaBotonAtender = false;
                $ocultarBotonNoSePresento = false;
                $puedeAtender = true;

                $fecha_hoy = date('Y-m-d');
                $fecha_turno = date('Y-m-d',strtotime($fecha));

                if ($fecha_turno <= $fecha_hoy) {


                    if ($idServicioSesion != $idServicioTurno) {

                        $servicioPasePrevioTurno = ServiciosEfector::find()
                            ->where(['id_efector' => Yii::$app->user->getIdEfector()])
                            ->andWhere(['id_servicio' => $idServicioTurno])
                            ->one();

                        //AQUI CHEQUEO SI EL ID SERVICIO EN SESION COINCIDE CON EL PASE PREVIO

                        if ($idServicioSesion != $servicioPasePrevioTurno->pase_previo) {
                            return '<h6><span class="text-danger"><b>Para realizar la atencion usted debe CAMBIAR de Servicio</b></span></h6>';
                        }

                        $ocultarBotonNoSePresento = true;

                        if (!Consulta::existeConsultaPasePrevio($id, $idServicioSesion)) {
                            $url = Consulta::armarUrlAConsultadesdeParent(Consulta::PARENT_PASE_PREVIO, $id, $idServicioSesion, $id_persona);
                        } else {
                            $ocultaBotonAtender = true;
                        }
                    } else {
                        $url = Consulta::armarUrlAConsultadesdeParent(Consulta::PARENT_TURNO, $id, $idServicioAsignado, $id_persona);
                    }
                } else {
                    $puedeAtender = false;
                }

                if ($puedeAtender) {

                    if (!$ocultaBotonAtender) {
                        $a = yii\helpers\Html::a(
                            '<b>Atender</b>',
                            $url,
                            [
                                'class' => 'btn btn-sm btn-outline-info rounded-pill atender',
                                'title' => 'Atender',
                            ]
                        );
                    } else {

                        $a = '<span class="badge bg-warning">PASE PREVIO COMPLETADO</span>';
                    }

                    if (!$ocultarBotonNoSePresento) {
                        $b = yii\helpers\Html::a(
                            'No se presentó',
                            ['turnos/no-se-presento'],
                            [
                                'class' => 'btn btn-sm btn-outline-danger rounded-pill ms-4 cambiar_estado_turno',
                                'alert_title' => 'Confirme la ausencia del paciente',
                                'title' => 'No se presentó',
                                'post_data' => '{"id_turno": ' . $id . '}'
                            ]
                        );
                    } else {
                        $b = '';
                    }

                    $c = '';
                    $d = '';

                    //TODO: CONTROLAR QUE EL SERVICIO DEFINIDO PARA PASE PREVIO TIENE UNA CONFIGURACION EN EL BACKEND.

                    if ($pase_previo == 27 || $pase_previo == 25) {

                        $c = yii\helpers\Html::a(
                            '<b>Nueva Atencion de Enfermeria</b>',
                            Consulta::armarUrlAConsultadesdeParent(Consulta::PARENT_PASE_PREVIO, $id, '', $id_persona),
                            [
                                'class' => 'btn btn-sm btn-outline-info rounded-pill ms-4 text-dark atender',
                                'title' => 'Atender',
                            ]
                        );

                        $consultaAE = ConsultaAtencionesEnfermeria::obtenerUltimaAtencionPorPaciente($id_persona);
                        if ($consultaAE) {

                            $d = yii\helpers\Html::button(
                                '<b>Ver última AE</b>',
                                [
                                    'class' => 'btn btn-sm btn-outline-info rounded-pill text-dark ms-4',
                                    'title' => 'Ver ultima atención de enfermeria',
                                    "data-bs-toggle" => "modal",
                                    "data-bs-target" => "#modal_detail_consulta",
                                    'data-bs-consulta_id' => $consultaAE->id_consulta,
                                    'data-bs-consulta_detalle_url' => Url::toRoute('consultas/view'),
                                ]
                            );
                        }
                    }
                } else {
                    $a = '<span class="badge bg-warning">NO SE PUEDE ATENDER TURNOS CON FECHA FUTURA</span>';
                    $b = '';
                    $c = '';
                    $d = '';
                }


                $footer = $a . $b . $c . $d;

                break;
            case self::ESTADO_SIN_ATENDER:
                $footer = '<h5><span class="text-danger">No se presentó</span></h5>';
                break;
            case self::ESTADO_CANCELADO:
                $footer = '<h5><span class="text-danger">Cancelado</span></h5>';
                break;
            case self::ESTADO_ATENDIDO:
                $footer = '<h5><span class="text-success">Atendido</span></h5>';
                break;
        }

        return $footer;
    }


    public static function paraEnfermeria($turno)
    {
        if ($turno->servicio->nombre == 'ENFERMERIA') {
            return true;
        } else {
            return false;
        }
    }


    //Este metodo me devuelve la cantidad de turnos otorgados a un rrhh en una fecha particular, siempre y cuando tenga los estados
    //PENDIENTE, EN_ATENCION o ATENDIDO

    public static function cantidadDeTurnosOtorgados($id_rrhh_servicio_asignado, $fecha){

        $cant_turnos = self::find()
        ->andWhere(['id_rrhh_servicio_asignado' => $id_rrhh_servicio_asignado])
        ->andWhere(['fecha' => $fecha])
        ->andWhere(['in','estado',Turno::ESTADOS_PARA_DESHABILITAR])
        ->count();

        return $cant_turnos;

    }

    /**
     * Query: indica si ya existe un turno activo en ese slot (para búsqueda de primer disponible).
     * Flujo MVC: usado por Controller/Component; las queries viven en el modelo.
     *
     * @param int $idRrhhServicioAsignado
     * @param string $fecha Y-m-d
     * @param string $hora HH:MM (se normaliza a HH:MM:00 internamente si hace falta)
     * @return bool
     */
    public static function estaOcupadoSlot($idRrhhServicioAsignado, $fecha, $hora)
    {
        $horaNormalizada = (strlen($hora) === 5 && strpos($hora, ':') !== false) ? $hora . ':00' : $hora;
        return static::findActive()
            ->andWhere([
                'id_rrhh_servicio_asignado' => (int) $idRrhhServicioAsignado,
                'fecha' => $fecha,
                'hora' => $horaNormalizada,
            ])
            ->exists();
    }


}
