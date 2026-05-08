<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Agenda por asignación profesional–efector–servicio.
 *
 * Tabla: `profesional_efector_servicio_agenda`
 *
 * @property int $id
 * @property int $id_profesional_efector_servicio
 * @property int $id_efector
 * @property string $formas_atencion
 * @property int|null $cupo_pacientes
 * @property int|null $duracion_slot_minutos
 * @property bool $acepta_consultas_online
 * @property string|null $lunes_2
 * @property string|null $martes_2
 * @property string|null $miercoles_2
 * @property string|null $jueves_2
 * @property string|null $viernes_2
 * @property string|null $sabado_2
 * @property string|null $domingo_2
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class ProfesionalEfectorServicioAgenda extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'profesional_efector_servicio_agenda';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => static function () {
                    return Yii::$app->user && Yii::$app->user->id ? (int) Yii::$app->user->id : null;
                },
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_profesional_efector_servicio', 'id_efector', 'formas_atencion'], 'required'],
            [['id_profesional_efector_servicio', 'id_efector', 'cupo_pacientes', 'duracion_slot_minutos'], 'integer'],
            [['acepta_consultas_online'], 'boolean'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['formas_atencion'], 'string', 'max' => 32],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'safe'],
        ];
    }

    public function getAsignacion()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }
}

