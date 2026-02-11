<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "agenda_rrhh".
 *
 * @property string $id_agenda_rrhh
 * @property string $id_rr_hh
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property string $lunes
 * @property string $martes
 * @property string $miercoles
 * @property string $jueves
 * @property string $viernes
 * @property string $sabado
 * @property string $domingo
 * @property string $id_tipo_dia
 * @property string $fecha_inicio
 * @property string $fecha_fin
 * @property integer $id_efector
 * @property bool $acepta_consultas_online Si el profesional acepta consultas por chat/videollamada en esta agenda
 */
class Agenda_rrhh extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public $id_persona;

    const FORMA_ATENCION_SIN_ATENCION = 'SIN_ATENCION';
    const FORMAS_ATENCION = ['ORDEN_LLEGADA' => 'Orden de llegada', 'TURNO' => 'Programado'];
    
    const CUPOS = [0 => 'Sin Cupo', 5 => 5, 10 => 10, 15 => 15, 20 => 20, 25 => 25, 30 => 30, 35 => 35, 40 => 40, 45 => 45, 50 => 50, 55 => 55, 80 => 80];

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'agenda_rrhh';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by']
                ],
                'value' => Yii::$app->user->id,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_rrhh_servicio_asignado', 'formas_atencion'], 'required',
              /*  'when' => function ($model) {
                    return $model->is_member == 2;
                },*/
            ],
            [['id_rrhh_servicio_asignado', 'id_tipo_dia', 'id_efector', 'cupo_pacientes'], 'integer'],
            [['acepta_consultas_online'], 'boolean'],
            [['hora_inicio', 'hora_fin', 'fecha_inicio', 'fecha_fin'], 'safe'],
            [['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo', 
            'lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2','formas_atencion'], 'string'],
            [['fecha_inicio', 'fecha_fin'], 'date', 'format' => 'php:Y-m-d'],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'validarAlmenosUno', 'skipOnEmpty' => false],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_agenda_rrhh' => 'Id Agenda Rrhh',
            'id_rrhh_servicio_asignado' => 'Servicio de Recurso humano',
            'hora_inicio' => 'Hora Inicio',
            'hora_fin' => 'Hora Fin',
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado' => 'Sábado',
            'domingo' => 'Domingo',
            'id_tipo_dia' => 'Tipo Día',
            'fecha_inicio' => 'Fecha Inicio',
            'fecha_fin' => 'Fecha Fin',
            'id_efector' => 'Efector',
            'acepta_consultas_online' => 'Acepta consultas online',
        ];
    }
    
    public function getRrhh()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public function getTipo_dia()
    {
        return $this->hasOne(Tipo_dia::className(), ['id_tipo_dia' => 'id_tipo_dia']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    public function getRrhhServicioAsignado()
    {
        return $this->hasOne(RrhhServicio::className(), ['id' => 'id_rrhh_servicio_asignado']);
    }

    public function getNombredeusuario($id)
    {
        $consulta_user = \webvimark\modules\UserManagement\models\User::findOne(['id' => $id]);
        $nombre_usuario = $consulta_user->username;

        return $nombre_usuario;
    }

    public static function validarGrupodeAgendas($agendas)
    {
        $arr_lunes = $arr_martes = $arr_miercoles = $arr_jueves = $arr_viernes = $arr_sabado = $arr_domingo = [];

        foreach ($agendas as $agenda) {
            if ($agenda->lunes_2 != "") {
                if (count(array_intersect($arr_lunes, explode(",", $agenda->lunes_2))) > 0) {
                    return false;
                } else {
                    $arr_lunes = array_merge($arr_lunes, explode(",", $agenda->lunes_2));
                }
            }
            if ($agenda->martes_2 != "") {
                if (count(array_intersect($arr_martes, explode(",", $agenda->martes_2))) > 0) {
                    return false;
                } else {
                    $arr_martes = array_merge($arr_martes, explode(",", $agenda->martes_2));
                }
            }
            if ($agenda->miercoles_2 != "") {
                if (count(array_intersect($arr_miercoles, explode(",", $agenda->miercoles_2))) > 0) {
                    return false;
                } else {
                    $arr_miercoles = array_merge($arr_miercoles, explode(",", $agenda->miercoles_2));
                }
            }
            if ($agenda->jueves_2 != "") {
                if (count(array_intersect($arr_jueves, explode(",", $agenda->jueves_2))) > 0) {
                    return false;
                } else {
                    $arr_jueves = array_merge($arr_jueves, explode(",", $agenda->jueves_2));
                }
            }
            if ($agenda->viernes_2 != "") {
                if (count(array_intersect($arr_viernes, explode(",", $agenda->viernes_2))) > 0) {
                    return false;
                } else {
                    $arr_viernes = array_merge($arr_viernes, explode(",", $agenda->viernes_2));
                }
            }
            if ($agenda->sabado_2 != "") {
                if (count(array_intersect($arr_sabado, explode(",", $agenda->sabado_2))) > 0) {
                    return false;
                } else {
                    $arr_sabado = array_merge($arr_sabado, explode(",", $agenda->sabado_2));
                }
            }
            if ($agenda->domingo_2 != "") {
                if (count(array_intersect($arr_domingo, explode(",", $agenda->domingo_2))) > 0) {
                    return false;
                } else {
                    $arr_domingo = array_merge($arr_domingo, explode(",", $agenda->domingo_2));
                }
            }
        }

        return true;
    }

    public function validarAlmenosUno()
    {
        if (
            (is_null($this->lunes_2) || $this->lunes_2 == "") &&
            (is_null($this->martes_2) || $this->martes_2 == "") &&
            (is_null($this->miercoles_2) || $this->miercoles_2 == "") &&
            (is_null($this->jueves_2) || $this->jueves_2 == "") &&
            (is_null($this->viernes_2) || $this->viernes_2 == "") &&
            (is_null($this->sabado_2) || $this->sabado_2 == "") &&
            (is_null($this->domingo_2) || $this->domingo_2 == "")
        ) {
            $this->clearErrors('id_rrhh_servicio_asignado');
            $this->addError('id_rrhh_servicio_asignado', 'La agenda para este servicio esta vacía');
        }
    }

    /**
     * Query: agendas por lista de id_rrhh_servicio_asignado (para búsqueda de slots).
     * Flujo MVC: usado por Controller/Component; las queries viven en el modelo.
     *
     * @param int[] $idsRrhhServicio
     * @return Agenda_rrhh[] indexado por id_rrhh_servicio_asignado
     */
    public static function findPorIdsRrhhServicio(array $idsRrhhServicio)
    {
        if (empty($idsRrhhServicio)) {
            return [];
        }
        return static::find()
            ->andWhere(['in', 'id_rrhh_servicio_asignado', $idsRrhhServicio])
            ->indexBy('id_rrhh_servicio_asignado')
            ->all();
    }

    /** Columnas de agenda por día de la semana (N=1..7) */
    private static function getColumnasAgenda()
    {
        return ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
    }

    /**
     * Slots (horas) disponibles para esta agenda en una fecha dada.
     * Usa columnas lunes_2..domingo_2 y cupo; lógica de negocio en el modelo.
     *
     * @param string $dia Fecha en Y-m-d
     * @return string[] Lista de horas en formato HH:MM
     */
    public function getSlotsParaDia($dia)
    {
        $columnas = static::getColumnasAgenda();
        $nroDiaSemana = (int) date('N', strtotime($dia)); // 1 = lunes, 7 = domingo
        $colAgenda = $columnas[$nroDiaSemana - 1] ?? null;
        if (!$colAgenda) {
            return [];
        }
        $colValue = $this->{$colAgenda};
        if (!$colValue) {
            return [];
        }
        $horariosAgenda = array_map('intval', explode(',', $colValue));
        if (empty($horariosAgenda)) {
            return [];
        }
        if (is_null($this->cupo_pacientes) || $this->cupo_pacientes == 0) {
            $minutosXPaciente = 15;
            $agregoSegundos = false;
            $cupoPacientes = count($horariosAgenda) * 5;
        } else {
            $minutosXPaciente = 60 * count($horariosAgenda) / $this->cupo_pacientes;
            $agregoSegundos = ($minutosXPaciente - (int) $minutosXPaciente) >= 0.5;
            $cupoPacientes = $this->cupo_pacientes;
        }
        return static::crearSlotsDesdeHorarios($horariosAgenda, $cupoPacientes, $minutosXPaciente, $agregoSegundos);
    }

    /**
     * Genera lista de slots HH:MM a partir de horarios de agenda (lógica de negocio en modelo).
     *
     * @param int[] $horariosAgenda
     * @param int $cupoPacientes
     * @param float|int $minutosXPaciente
     * @param bool $agregoSegundos
     * @return string[]
     */
    public static function crearSlotsDesdeHorarios(array $horariosAgenda, $cupoPacientes, $minutosXPaciente, $agregoSegundos)
    {
        $intervalos = [];
        $intervaloActual = [];
        for ($i = 0; $i < count($horariosAgenda); $i++) {
            if (empty($intervaloActual)) {
                $intervaloActual[] = $horariosAgenda[$i];
            } else {
                if ($horariosAgenda[$i] === $intervaloActual[count($intervaloActual) - 1] + 1) {
                    $intervaloActual[] = $horariosAgenda[$i];
                } else {
                    $intervalos[] = $intervaloActual;
                    $intervaloActual = [$horariosAgenda[$i]];
                }
            }
        }
        if (!empty($intervaloActual)) {
            $intervalos[] = $intervaloActual;
        }
        $slots = [];
        $minutosXPaciente = (int) $minutosXPaciente;
        foreach ($intervalos as $horarios) {
            $inicio = new \DateTime(sprintf('%02d:00', $horarios[0]));
            $ultHora = new \DateTime(sprintf('%02d:00', $horarios[count($horarios) - 1]));
            $fin = clone $ultHora;
            $fin->modify('+60 minutes');
            while ($inicio < $fin && $cupoPacientes > 0) {
                $slots[] = $inicio->format('H:i');
                if ($agregoSegundos) {
                    $inicio->modify("+{$minutosXPaciente} minutes 30 seconds");
                } else {
                    $inicio->modify("+{$minutosXPaciente} minutes");
                }
                $cupoPacientes--;
            }
        }
        return $slots;
    }

}
