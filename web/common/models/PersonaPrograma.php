<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "persona_programa".
 *
 * @property int $id
 * @property int $id_persona
 * @property int $id_programa
 * @property string|null $clave_beneficiario
 * @property string|null $activo
 * @property string|null $fecha
 * @property string|null $fecha_baja
 * @property string|null $motivo_baja
 * @property string|null $tipo_empadronamiento
 * @property int $id_rrhh_efector Es el recurso humano que cargo la ficha
 *
 * @property Personas $persona
 * @property Programas $programa
 * @property PersonaProgramaDiabetes[] $personaProgramaDiabetes
 */
class PersonaPrograma extends \yii\db\ActiveRecord
{

    CONST TIPO_EMPADRONAMIENTO = ['ALTA' => 'Alta', 'REEMPADRONAMIENTO' => 'Reempadronamiento', 'RENOVACION' => 'Renovacion'];
    CONST ACTIVO_SI = 'SI';
    CONST ACTIVO_NO = 'NO';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'persona_programa';
    }

    public function behaviors()
    {
        return[
            'fechas' => [
                'class' => 'yii\behaviors\AttributesBehavior',
                'attributes' => [                    
                   'fecha' => [
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
                    ],
                    'fecha_baja' => [
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
            [['id_persona', 'id_programa', 'id_rrhh_efector'], 'required'],
            [['id_persona', 'id_programa', 'id_rrhh_efector'], 'integer'],
            [['activo', 'tipo_empadronamiento'], 'string'],
            [['fecha', 'fecha_baja'], 'safe'],
            [['clave_beneficiario'], 'string', 'max' => 16],
            [['motivo_baja'], 'string', 'max' => 200],
            [['id_persona'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::className(), 'targetAttribute' => ['id_persona' => 'id_persona']],
            [['id_programa'], 'exist', 'skipOnError' => true, 'targetClass' => Programa::className(), 'targetAttribute' => ['id_programa' => 'id_programa']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Id Persona',
            'id_programa' => 'Programa',
            'clave_beneficiario' => 'Clave Beneficiario',
            'activo' => 'Activo',
            'fecha' => 'Fecha',
            'fecha_baja' => 'Fecha Baja',
            'motivo_baja' => 'Motivo Baja',
            'tipo_empadronamiento' => 'Tipo Empadronamiento',
            'id_rrhh_efector' => 'Id Rrhh Efector',
        ];
    }

    /**
     * Gets query for [[Persona]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * Gets query for [[Programa]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPrograma()
    {
        return $this->hasOne(Programa::className(), ['id_programa' => 'id_programa']);
    }

    /**
     * Gets query for [[PersonaProgramaDiabetes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPersonaProgramaDiabetes()
    {
        return $this->hasMany(PersonaProgramaDiabetes::className(), ['id_persona_programa' => 'id']);
    }

    public function afterFind () {

        if (preg_match('/[1-9]/', $this->fecha)) {
            $this->fecha = Yii::$app->formatter->asDate($this->fecha, Yii::$app->formatter->dateFormat);
        }
        else {
            $this->fecha = null;
        }

        if (preg_match('/[1-9]/', $this->fecha_baja)) {
            $this->fecha_baja = Yii::$app->formatter->asDate($this->fecha_baja, Yii::$app->formatter->dateFormat);
        } 
        else {
            $this->fecha_baja = null;
        }

        parent::afterFind ();
    }

    public static function personaEmpadronada($id_persona, $id_programa){

        $persona = self::find()
            ->where(['id_persona' => $id_persona])
            ->andWhere(['id_programa' => $id_programa])
            ->one();

        if($persona){
            return true;
        }
        else{
            return false;
        }    
    }
}
