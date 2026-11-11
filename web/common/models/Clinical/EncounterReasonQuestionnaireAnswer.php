<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Respuesta del cuestionario pre-turno (tabla `interaccion_motivos_cuestionario`).
 *
 * Una fila por pregunta del catálogo respondida por el paciente. El cierre libre es la pregunta
 * `AppointmentReasonQuestionnaireCatalog::CLOSING_ID`.
 *
 * @property int $id
 * @property int $encounter_id
 * @property string $question_id
 * @property string|null $option_id
 * @property string|null $texto_libre
 * @property int|null $answered_by_user_id
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @property Encounter $encounter
 */
class EncounterReasonQuestionnaireAnswer extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%interaccion_motivos_cuestionario}}';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'question_id'], 'required'],
            [['encounter_id', 'answered_by_user_id'], 'integer'],
            [['texto_libre'], 'string', 'max' => 500],
            [['question_id', 'option_id'], 'string', 'max' => 64],
            [['created_at', 'updated_at'], 'safe'],
            [['encounter_id'], 'exist', 'skipOnError' => true, 'targetClass' => Encounter::class, 'targetAttribute' => ['encounter_id' => 'id']],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    /**
     * @return self[]
     */
    public static function findForEncounter(int $encounterId): array
    {
        return self::find()
            ->where(['encounter_id' => $encounterId])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        $this->updated_at = date('Y-m-d H:i:s');

        return true;
    }
}
