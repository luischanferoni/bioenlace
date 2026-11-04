<?php

namespace common\models\Person;

use common\models\Person\Persona;

use Yii;

/**
 * This is the model class for table "persona_telefono".
 *
 * @property string $id_persona_telefono
 * @property integer $id_persona
 * @property string $id_tipo_telefono
 * @property string $numero
 * @property string $comentario
 *
 * @property-read Persona|null $persona
 * @property TipoTelefono $idTipoTelefono
 */
class PersonaTelefono extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */

    
    public static function tableName()
    {
        return 'persona_telefono';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
             [[ 'id_tipo_telefono', 'numero'], 'required'],
            [['id_persona', 'id_tipo_telefono'], 'integer'],
            [['comentario'], 'string'],
            [['numero'], 'string', 'max' => 20]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_persona_telefono' => 'Id Persona Telefono',
            'id_persona' => 'Id Persona',
            'id_tipo_telefono' => 'Tipo de Telefono',
            'numero' => 'Numero',
            'comentario' => 'Comentario',
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
    public function getTipoTelefono()
    {
        return $this->hasOne(Tipo_telefono::className(), ['id_tipo_telefono' => 'id_tipo_telefono']);
    }
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTelefonosPorPersona($id_persona)
    {
        return static::find()
            ->where(['id_persona' => (int) $id_persona])
            ->asArray()
            ->all();
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // El alta legacy vincula la persona por query string (?idp=<id_persona>).
        if ($insert && (int) $this->id_persona <= 0) {
            $idp = (int) Yii::$app->request->get('idp', 0);
            if ($idp > 0) {
                $this->id_persona = $idp;
            }
        }

        return true;
    }

}
