<?php

namespace common\models\Clinical;

use common\models\Person\Persona;

/**
 * This is the model class for table "valoracion_nutricional".
 *
 * @property string $id_valoracion
 * @property integer $id_persona
 * @property string $fecha
 * @property string $peso
 * @property string $per_peso
 * @property string $talla
 * @property string $per_talla
 * @property string $perim_cefalico
 * @property string $per_perim_cefalico
 * @property string $imc
 * @property string $per_imc
 * @property int $encounter_id
 *
 * @property-read Encounter|null $encounter
 * @property-read Persona|null $persona
 */
class ValoracionNutricional extends \yii\db\ActiveRecord
{
    use \common\traits\EncounterIdTrait;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'valoracion_nutricional';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'peso', 'talla', 'perim_cefalico', 'encounter_id'], 'integer'],
            [['fecha'], 'safe'],
            [['imc'], 'number'],
            [['encounter_id'], 'required'],
            [['per_peso', 'per_talla', 'per_perim_cefalico', 'per_imc'], 'string', 'max' => 10]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_valoracion' => 'Id Valoracion',
            'id_persona' => 'Id Persona',
            'fecha' => 'Fecha',
            'peso' => 'Peso',
            'per_peso' => 'Per Peso',
            'talla' => 'Talla',
            'per_talla' => 'Per Talla',
            'perim_cefalico' => 'Perimetro Cefalico (PC)',
            'per_perim_cefalico' => 'Percentilo (PC)',
            'imc' => 'Imc',
            'per_imc' => 'Per Imc',
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
    
    public static function getValoracionPorEncounter(int $encounterId): ?self
    {
        return static::findOne(['encounter_id' => $encounterId]);
    }
    
}
