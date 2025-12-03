<?php

namespace frontend\modules\mapear\models;

use Yii;

/**
 * This is the model class for table "condicion".
 *
 * @property int $id
 * @property int $id_ecl_snomed
 * @property int $id_regla
 */
class Condicion extends MapearActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'condicion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_ecl_snomed', 'id_regla'], 'required'],
            [['id_ecl_snomed','id_regla'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_ecl_snomed' => 'Snomed',
            'id_regla' => 'Regla',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LaboratorioQuery the active query used by this AR class.
     */
    // public static function find()
    // {
    //     return new Condicion(get_called_class());
    // }

    public function getEcl()
    {
        return $this->hasOne(EclSnomed::className(), ['id' => 'id_ecl_snomed']);
    }

    public function getRegla()
    {
        return $this->hasOne(Regla::className(), ['id' => 'id_ecl_snomed']);
    }
}
