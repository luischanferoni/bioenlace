<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Condición laboral por asignación PES (`profesional_efector_servicio`).
 *
 * @property int $id
 * @property int $id_profesional_efector_servicio
 * @property int $id_condicion_laboral
 * @property string|null $fecha_inicio
 * @property string|null $fecha_fin
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class ProfesionalEfectorServicioCondicionLaboral extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName(): string
    {
        return 'profesional_efector_servicio_condicion_laboral';
    }

    public function behaviors(): array
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

    public function rules(): array
    {
        return [
            [['id_profesional_efector_servicio', 'id_condicion_laboral'], 'required'],
            [['id_profesional_efector_servicio', 'id_condicion_laboral', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['fecha_inicio', 'fecha_fin', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['fecha_fin'], 'validateFechaFin'],
        ];
    }

    public function validateFechaFin(): void
    {
        if ($this->fecha_fin === null || $this->fecha_fin === '') {
            return;
        }
        if ($this->fecha_inicio === null || $this->fecha_inicio === '') {
            return;
        }
        if (strtotime((string) $this->fecha_fin) <= strtotime((string) $this->fecha_inicio)) {
            $this->addError('fecha_fin', 'La fecha de fin debe ser posterior a la de inicio.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id_profesional_efector_servicio' => 'Asignación PES',
            'id_condicion_laboral' => 'Condición laboral',
            'fecha_inicio' => 'Inicio',
            'fecha_fin' => 'Fin',
        ];
    }

    public function getProfesionalEfectorServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    public function getCondicionLaboral(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Condiciones_laborales::class, ['id_condicion_laboral' => 'id_condicion_laboral']);
    }

    /**
     * Última fila activa por PES (mayor id), alineado al comportamiento previo con `rrhh_laboral`.
     */
    public static function findUltimaActivaPorPes(int $idPes): ?self
    {
        if ($idPes <= 0) {
            return null;
        }
        /** @var self|null $row */
        $row = static::find()
            ->where(['id_profesional_efector_servicio' => $idPes, 'deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row;
    }

    /**
     * ¿Existe al menos una condición laboral activa para algún PES de la persona en el efector?
     */
    public static function existeAlgunaActivaParaPersonaEfector(int $idPersona, int $idEfector): bool
    {
        if ($idPersona <= 0 || $idEfector <= 0) {
            return false;
        }

        return static::find()
            ->alias('cl')
            ->innerJoin(
                ['pes' => ProfesionalEfectorServicio::tableName()],
                'pes.id = cl.id_profesional_efector_servicio AND pes.deleted_at IS NULL'
            )
            ->where([
                'pes.id_persona' => $idPersona,
                'pes.id_efector' => $idEfector,
                'cl.deleted_at' => null,
            ])
            ->exists();
    }
}
