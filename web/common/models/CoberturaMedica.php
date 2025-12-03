<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "cobertura_medica".
 *
 * @property int $codigo
 * @property int|null $rnos
 * @property string $nombre
 */
class CoberturaMedica extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'cobertura_medica';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['codigo', 'nombre'], 'required'],
            [['codigo', 'rnos'], 'integer'],
            [['nombre'], 'string', 'max' => 164],
            [['codigo'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'codigo' => 'Codigo',
            'rnos' => 'RNOS',
            'nombre' => 'Nombre',
        ];
    }

    public static function getCoberturasForSelect($include_only=Null) {
        $query = CoberturaMedica::find()
            ->alias('cm')
            ->select(['cm.codigo', 'cm.nombre']);
        if($include_only !== null) {
            $query->where(['or',
                ['cm.rnos' => $include_only],
                ['cm.codigo' => $include_only]
            ]);
        }
        return $query->orderBy('cm.nombre')
            ->all();
    }
}
