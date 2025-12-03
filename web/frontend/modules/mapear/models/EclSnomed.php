<?php

namespace frontend\modules\mapear\models;

use Yii;

/**
 * This is the model class for table "ecl_snomed".
 *
 * @property int $id
 * @property string $ecl
 * @property string $categoria
 */
class EclSnomed extends MapearActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ecl_snomed';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ecl', 'categoria'], 'required'],
            [['ecl', 'categoria'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ecl' => 'Ecl',
            'categoria' => 'Categoria',
        ];
    }
    
    public function getCondiciones()
    {
        return $this->hasMany(Condicion::className(), ['id_ecl_snomed' => 'id']);
    }
    
    /**
     * {@inheritdoc}
     * @return LaboratorioQuery the active query used by this AR class.
     */
//     public static function find()
//     {
//         return new EclSnomed(get_called_class());
//     }

}