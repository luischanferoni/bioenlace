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
 * @property int|null $intervalo_minutos
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
    use \common\traits\AgendaHorarioSlotsTrait;

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
            [['id_profesional_efector_servicio', 'id_efector', 'cupo_pacientes', 'duracion_slot_minutos', 'intervalo_minutos'], 'integer'],
            ['intervalo_minutos', 'validateIntervaloMinutos'],
            [['acepta_consultas_online'], 'boolean'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['formas_atencion'], 'string', 'max' => 32],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'safe'],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'validarAlmenosUnDiaHorario', 'skipOnEmpty' => false],
        ];
    }

    public function validateIntervaloMinutos(): void
    {
        if ($this->intervalo_minutos === null || $this->intervalo_minutos === '') {
            return;
        }
        if (!\common\components\Services\ProfesionalEfectorServicio\AgendaIntervaloMinutos::isAllowed((int) $this->intervalo_minutos)) {
            $this->addError('intervalo_minutos', 'Intervalo no permitido. Use 15, 20, 30, 45 o 60 minutos.');
        }
    }

    public function validarAlmenosUnDiaHorario(): void
    {
        if (
            (is_null($this->lunes_2) || $this->lunes_2 === '') &&
            (is_null($this->martes_2) || $this->martes_2 === '') &&
            (is_null($this->miercoles_2) || $this->miercoles_2 === '') &&
            (is_null($this->jueves_2) || $this->jueves_2 === '') &&
            (is_null($this->viernes_2) || $this->viernes_2 === '') &&
            (is_null($this->sabado_2) || $this->sabado_2 === '') &&
            (is_null($this->domingo_2) || $this->domingo_2 === '')
        ) {
            $this->addError('formas_atencion', 'La agenda para este servicio está vacía');
        }
    }

    public function getAsignacion()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    /**
     * @param int[] $idsProfesionalEfectorServicio
     * @return array<int, self> indexado por id_profesional_efector_servicio
     */
    public static function findPorIdsProfesionalEfectorServicio(array $idsProfesionalEfectorServicio): array
    {
        if ($idsProfesionalEfectorServicio === []) {
            return [];
        }

        return static::find()
            ->andWhere(['in', 'id_profesional_efector_servicio', $idsProfesionalEfectorServicio])
            ->andWhere(['deleted_at' => null])
            ->indexBy('id_profesional_efector_servicio')
            ->all();
    }

    public static function findActivaPorProfesionalEfectorServicio(int $idPes): ?self
    {
        /** @var self|null $row */
        $row = static::find()
            ->where(['id_profesional_efector_servicio' => $idPes, 'deleted_at' => null])
            ->one();

        return $row;
    }
}

