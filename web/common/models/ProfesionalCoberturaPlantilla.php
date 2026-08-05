<?php

namespace common\models;

use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\Clinical\Encounter;
use Yii;
use yii\db\ActiveRecord;

/**
 * Plantilla semanal de cobertura EMER/IMP (rangos por día → materializa a profesional_cobertura).
 *
 * @property int $id
 * @property int $id_persona
 * @property int $id_efector
 * @property int|null $id_servicio
 * @property int|null $id_profesional_efector_servicio
 * @property string $encounter_class
 * @property string $vigente_desde
 * @property int $semanas
 * @property string|null $lunes_2
 * @property string|null $martes_2
 * @property string|null $miercoles_2
 * @property string|null $jueves_2
 * @property string|null $viernes_2
 * @property string|null $sabado_2
 * @property string|null $domingo_2
 * @property string $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class ProfesionalCoberturaPlantilla extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const DAY_COLUMNS = [
        1 => 'lunes_2',
        2 => 'martes_2',
        3 => 'miercoles_2',
        4 => 'jueves_2',
        5 => 'viernes_2',
        6 => 'sabado_2',
        7 => 'domingo_2',
    ];

    public static function tableName()
    {
        return 'profesional_cobertura_plantilla';
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
                    return Yii::$app->has('user', true) && Yii::$app->user->id
                        ? (int) Yii::$app->user->id
                        : null;
                },
            ],
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'value' => static function () {
                    return date('Y-m-d H:i:s');
                },
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_persona', 'id_efector', 'encounter_class', 'vigente_desde', 'semanas'], 'required'],
            [['id_persona', 'id_efector', 'id_servicio', 'id_profesional_efector_servicio', 'semanas'], 'integer'],
            [['vigente_desde', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['encounter_class'], 'string', 'max' => 10],
            [['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'], 'string', 'max' => 128],
            ['semanas', 'integer', 'min' => 1, 'max' => 12],
            ['encounter_class', 'in', 'range' => [Encounter::ENCOUNTER_CLASS_EMER, Encounter::ENCOUNTER_CLASS_IMP]],
            ['encounter_class', 'validateEncounterClass'],
        ];
    }

    public function validateEncounterClass(): void
    {
        if (!AgendaByEncounterClassMetadata::isCoberturaClass((string) $this->encounter_class)) {
            $this->addError('encounter_class', 'La plantilla solo admite EMER o IMP.');
        }
    }

    public function attributeLabels()
    {
        return [
            'vigente_desde' => 'Vigente desde',
            'semanas' => 'Semanas a generar',
            'encounter_class' => 'Tipo de cobertura',
        ];
    }
}
