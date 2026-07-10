<?php

namespace common\models;

use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use Yii;
use yii\db\ActiveRecord;

/**
 * Cobertura / roster de personal para EMER e IMP (entrada–salida).
 *
 * Tabla: `profesional_cobertura`
 * No genera cupos ni turnos de paciente.
 *
 * @property int $id
 * @property int $id_persona
 * @property int $id_efector
 * @property int|null $id_servicio
 * @property int|null $id_profesional_efector_servicio
 * @property string $encounter_class
 * @property string $inicio
 * @property string $fin
 * @property string|null $rol
 * @property string|null $notas
 * @property string $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class ProfesionalCobertura extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'profesional_cobertura';
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
            [['id_persona', 'id_efector', 'encounter_class', 'inicio', 'fin'], 'required'],
            [['id_persona', 'id_efector', 'id_servicio', 'id_profesional_efector_servicio'], 'integer'],
            [['inicio', 'fin', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['encounter_class'], 'string', 'max' => 10],
            [['rol'], 'string', 'max' => 64],
            [['notas'], 'string', 'max' => 255],
            ['encounter_class', 'validateEncounterClass'],
            ['fin', 'validateIntervalo'],
        ];
    }

    public function validateEncounterClass(): void
    {
        if (!AgendaByEncounterClassMetadata::isCoberturaClass((string) $this->encounter_class)) {
            $this->addError(
                'encounter_class',
                'La cobertura solo admite clases ' . implode(', ', AgendaByEncounterClassMetadata::coberturaClasses()) . '.'
            );
        }
    }

    public function validateIntervalo(): void
    {
        $inicio = strtotime((string) $this->inicio);
        $fin = strtotime((string) $this->fin);
        if ($inicio === false || $fin === false) {
            $this->addError('fin', 'inicio y fin deben ser fechas/horas válidas.');

            return;
        }
        if ($fin <= $inicio) {
            $this->addError('fin', 'La hora de salida debe ser posterior a la de entrada.');
        }
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Persona',
            'id_efector' => 'Efector',
            'id_servicio' => 'Servicio',
            'id_profesional_efector_servicio' => 'PES',
            'encounter_class' => 'Clase de encuentro',
            'inicio' => 'Entrada',
            'fin' => 'Salida',
            'rol' => 'Rol en el turno',
            'notas' => 'Notas',
        ];
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }

    public function getServicio()
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'id_servicio']);
    }

    public function getAsignacion()
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    /**
     * Solapes activos de la misma persona en el efector (cualquier clase de cobertura).
     *
     * @return list<self>
     */
    public static function findSolapes(
        int $idPersona,
        int $idEfector,
        string $inicio,
        string $fin,
        ?int $excludeId = null
    ): array {
        if ($idPersona <= 0 || $idEfector <= 0 || $inicio === '' || $fin === '') {
            return [];
        }

        $query = static::find()
            ->andWhere([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->andWhere(['<', 'inicio', $fin])
            ->andWhere(['>', 'fin', $inicio]);

        if ($excludeId !== null && $excludeId > 0) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        /** @var list<self> $rows */
        $rows = $query->orderBy(['inicio' => SORT_ASC])->all();

        return $rows;
    }
}
