<?php

namespace common\models\Clinical;

use common\models\Person\Persona;

/**
 * This is the model class for table "tension_arterial".
 *
 * @property string $id_tension
 * @property integer $id_persona
 * @property string $fecha
 * @property integer $sistolica
 * @property integer $diastolica
 * @property int $encounter_id
 *
 * @property-read Encounter|null $encounter
 * @property-read Persona|null $persona
 */
class TensionArterial extends \yii\db\ActiveRecord
{
    use \common\traits\EncounterIdTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tension_arterial';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'encounter_id'], 'required'],
            [['id_persona', 'sistolica', 'diastolica', 'encounter_id'], 'integer'],
            [['fecha'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tension' => 'Id Tension',
            'id_persona' => 'Id Persona',
            'fecha' => 'Fecha',
            'sistolica' => 'Sistólica',
            'diastolica' => 'Diastólica',
            'encounter_id' => 'Encounter',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }
    
    public static function getTensionPorEncounter(int $encounterId): ?self
    {
        return static::findOne(['encounter_id' => $encounterId]);
    }
}
