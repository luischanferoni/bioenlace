<?php

namespace common\models\Person;

use common\models\Person\Persona;

use Yii;

/**
 * This is the model class for table "persona_domicilios_vinculo".
 *
 * @property string $id_domicilio
 * @property integer $id_persona
 * @property string $activo
 * @property string $usuario_alta
 * @property string $fecha_alta
 *
 * @property-read Persona|null $persona
 * @property Domicilios $idDomicilio
 */
class Persona_domicilio extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'persona_domicilios_vinculo';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_domicilio', 'id_persona'], 'required'],
            [['id_domicilio', 'id_persona'], 'integer'],
            [['activo'], 'string'],
            [['fecha_alta'], 'safe'],
            [['usuario_alta'], 'string', 'max' => 40]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_domicilio' => 'Id Domicilio',
            'id_persona' => 'Id Persona',
            'activo' => 'Activo',
            'usuario_alta' => 'Usuario Alta',
            'fecha_alta' => 'Fecha Alta',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPersona()
    {
        return $this->hasOne(Persona::className(), ['id_persona' => 'id_persona']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDomicilio()
    {
        return $this->hasOne(Domicilio::className(), ['id_domicilio' => 'id_domicilio']);
    }
    
    
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // El alta legacy vincula la persona por query string (?idp=<id_persona>).
        if ($insert) {
            if ((int) $this->id_persona <= 0) {
                $idp = (int) Yii::$app->request->get('idp', 0);
                if ($idp > 0) {
                    $this->id_persona = $idp;
                }
            }
            if ((string) $this->fecha_alta === '') {
                $this->fecha_alta = date('Y-m-d');
            }
        }

        return true;
    }
}
