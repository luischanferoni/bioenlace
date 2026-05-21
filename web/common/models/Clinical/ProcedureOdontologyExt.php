<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Extensión odontológica 1:1 con {@see Procedure}.
 */
class ProcedureOdontologyExt extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'procedure_odontology_ext';
    }

    public function rules(): array
    {
        return [
            [['procedure_id'], 'required'],
            [['procedure_id'], 'integer'],
            [['tooth_number'], 'string', 'max' => 8],
            [['surfaces'], 'string', 'max' => 32],
            [['time_qualifier'], 'string', 'max' => 16],
        ];
    }

    public function getProcedure(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Procedure::class, ['id' => 'procedure_id']);
    }
}
