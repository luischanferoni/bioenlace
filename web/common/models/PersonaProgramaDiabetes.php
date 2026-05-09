<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "persona_programa_diabetes".
 *
 * @property int $id
 * @property int $id_persona_programa
 * @property string $tipo_diabetes
 * @property string|null $incluir_salud
 * @property int|null $id_persona_autorizada
 * @property string|null $parentesco_persona_autorizada
 * @property int|null $ins_lenta_nph
 * @property int|null $ins_lenta_lantus
 * @property int|null $ins_rapida_novorapid
 * @property int|null $metformina_500
 * @property int|null $metformina_850
 * @property int|null $glibenclamida
 * @property string|null $tiras
 * @property string|null $monitor
 * @property int|null $lanceta
 * @property int|null $id_efector Efector del médico solicitante
 * @property int|null $id_profesional_efector_servicio Médico que firma / solicita (PES)
 * @property int|null $hba1c
 * @property int|null $glucemia
 *
 * @property-read DispensaProgramaDiabetes[] $dispensas
 * @property-read PersonaPrograma|null $empadronamiento Alta en programa de salud que agrupa esta ficha diabetes
 * @property-read Persona|null $personaAutorizada
 */
class PersonaProgramaDiabetes extends \yii\db\ActiveRecord
{

    const TIPO_DIABETES = ['46635009' => 'diabetes mellitus tipo 1', '44054006' => 'diabetes mellitus tipo 2', '11687002' => 'diabetes mellitus gestacional', 'otro' => 'Otro'];

    const INCLUIR_SALUD = ['SI' => 'Si', 'NO' => 'No'];

    const PARENTESCO = ['PADRE' => 'PADRE', 'MADRE' => 'MADRE','SUEGRO/A' => 'SUEGRO/A', 'HIJO/A' => 'HIJO/A', 'YERNO' => 'YERNO', 'ABUELO/A' => 'ABUELO/A', 
    'NIETO/A' => 'NIETO/A', 'HERMANO/A' => 'HERMANA/A', 'CUÑADO/A' => 'CUÑADO/A', 'BISABUELO/A' => 'BISABUELO/A', 'BIZNIETO' => 'BIZNIETO', 'TIO/A' => 'TIO/A',
    'SOBRINO/A' => 'SOBRINO/A', 'OTRO' => 'OTRO'];


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'persona_programa_diabetes';
    }


    public function behaviors()
    {
        return [
            'fechas' => [
                'class' => 'yii\behaviors\AttributesBehavior',
                'attributes' => [
                    'fecha_laboratorio' => [
                        \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => function ($event, $attribute) {
                            if ($this->$attribute == "") {
                                return $this->$attribute;
                            }

                            if ($this->hasErrors($attribute)) {
                                return "";
                            }
                            $fecha = date_create_from_format('d/m/Y', $this->$attribute);
                            return date_format($fecha, 'Y-m-d');
                        }
                    ]
                ]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_persona_programa', 'tipo_diabetes'], 'required'],
            [['id_persona_programa', 'id_persona_autorizada', 'ins_lenta_nph', 'ins_lenta_lantus', 'ins_rapida_novorapid', 'metformina_500', 'metformina_850',
             'glibenclamida', 'lanceta', 'hba1c', 'glucemia', 'id_efector', 'id_profesional_efector_servicio', 'dni_persona_autorizada'], 'integer'],
            [['incluir_salud', 'tiras', 'monitor', 'nombre_persona_autorizada', 'apellido_persona_autorizada'], 'string'],
            [['fecha_laboratorio'], 'safe'],
            [['tipo_diabetes'], 'string', 'max' => 25],
            [['parentesco_persona_autorizada'], 'string', 'max' => 45],
            [['id_persona_programa'], 'exist', 'skipOnError' => true, 'targetClass' => PersonaPrograma::className(), 'targetAttribute' => ['id_persona_programa' => 'id']],
            [['id_persona_autorizada'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona_autorizada' => 'id_persona']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona_programa' => 'Id Persona Programa',
            'tipo_diabetes' => 'Tipo Diabetes',
            'incluir_salud' => 'Incluir Salud',
            'id_persona_autorizada' => 'Id Persona Autorizada',
            'parentesco_persona_autorizada' => 'Parentesco Persona Autorizada',
            'ins_lenta_nph' => 'Ins Lenta Nph',
            'ins_lenta_lantus' => 'Ins Lenta Lantus',
            'ins_rapida_novorapid' => 'Ins Rapida Novorapid',
            'metformina_500' => 'Metformina 500',
            'metformina_850' => 'Metformina 850',
            'glibenclamida' => 'Glibenclamida',
            'tiras' => 'Tiras',
            'monitor' => 'Monitor',
            'lanceta' => 'Lanceta',
            'id_efector' => 'Efector',
            'id_profesional_efector_servicio' => 'Profesional',
            'hba1c' => 'Hba1c',
            'glucemia' => 'Glucemia',
        ];
    }

    /**
     * Historial de dispensas asociadas a esta ficha diabetes.
     */
    public function getDispensas()
    {
        return $this->hasMany(DispensaProgramaDiabetes::className(), ['id_persona_programa_diabetes' => 'id']);
    }

    /**
     * Alias histórico (`dispensaProgramaDiabetes`).
     */
    public function getDispensaProgramaDiabetes()
    {
        return $this->getDispensas();
    }

    /**
     * Empadronamiento en programa de salud ({@see PersonaPrograma}) del que depende esta ficha.
     */
    public function getEmpadronamiento()
    {
        return $this->hasOne(PersonaPrograma::className(), ['id' => 'id_persona_programa']);
    }

    /**
     * Alias histórico (`personaPrograma`).
     */
    public function getPersonaPrograma()
    {
        return $this->getEmpadronamiento();
    }

    /**
     * Gets query for [[PersonaAutorizada]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaAutorizada()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona_autorizada']);
    }

    public function getProfesionalEfectorServicio()
    {
        return $this->hasOne(ProfesionalEfectorServicio::className(), ['id' => 'id_profesional_efector_servicio']);
    }

    public function afterFind () {

        if (preg_match('/[1-9]/', $this->fecha_laboratorio)) {
            $this->fecha_laboratorio = Yii::$app->formatter->asDate($this->fecha_laboratorio, Yii::$app->formatter->dateFormat);
        }
        else {
            $this->fecha_laboratorio = null;
        }

        parent::afterFind ();
    }

}
