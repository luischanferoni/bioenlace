<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "dispensa_programa_diabetes".
 *
 * @property int $id
 * @property int $id_persona_programa_diabetes
 * @property int $id_persona_retira
 * @property string|null $fecha_retiro
 * @property int|null $ins_lenta_nph
 * @property int|null $ins_lenta_lantus
 * @property int|null $ins_rapida_novorapid
 * @property int|null $metformina_500
 * @property int|null $metformina_850
 * @property int|null $glibenclamida
 * @property string|null $tiras
 * @property int|null $monitor
 * @property int|null $lanceta
 * @property int|null $id_rrhh_efector Persona que entrega los medicamentos
 *
 * @property PersonaProgramaDiabetes $personaProgramaDiabetes
 * @property Personas $personaRetira
 */
class DispensaProgramaDiabetes extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'dispensa_programa_diabetes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_persona_programa_diabetes', 'id_persona_retira'], 'required'],
            [['id_persona_programa_diabetes', 'id_persona_retira', 'ins_lenta_nph', 'ins_lenta_lantus', 'ins_rapida_novorapid', 'metformina_500', 'metformina_850', 'glibenclamida', 'monitor', 'lanceta', 'id_rrhh_efector'], 'integer'],
            [['fecha_retiro'], 'safe'],
            [['tiras'], 'string'],
            [['id_persona_programa_diabetes'], 'exist', 'skipOnError' => true, 'targetClass' => PersonaProgramaDiabetes::className(), 'targetAttribute' => ['id_persona_programa_diabetes' => 'id']],
            [['id_persona_retira'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona_retira' => 'id_persona']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona_programa_diabetes' => 'Id Persona Programa Diabetes',
            'id_persona_retira' => 'Id Persona Retira',
            'fecha_retiro' => 'Fecha Retiro',
            'ins_lenta_nph' => 'Ins Lenta Nph',
            'ins_lenta_lantus' => 'Ins Lenta Lantus',
            'ins_rapida_novorapid' => 'Ins Rapida Novorapid',
            'metformina_500' => 'Metformina 500',
            'metformina_850' => 'Metformina 850',
            'glibenclamida' => 'Glibenclamida',
            'tiras' => 'Tiras',
            'monitor' => 'Monitor',
            'lanceta' => 'Lanceta',
            'id_rrhh_efector' => 'Id Rrhh Efector',
        ];
    }

    /**
     * Gets query for [[PersonaProgramaDiabetes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaProgramaDiabetes()
    {
        return $this->hasOne(PersonaProgramaDiabetes::className(), ['id' => 'id_persona_programa_diabetes']);
    }

    /**
     * Gets query for [[PersonaRetira]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaRetira()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona_retira']);
    }
}
