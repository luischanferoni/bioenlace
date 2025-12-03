<?php

namespace common\models\snomed;

use Yii;

/**
 * This is the model class for table "snomed_situacion".
 *
 * @property integer $conceptId
 * @property string $term
 */
class SnomedSituacion extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'snomed_situacion';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['conceptId', 'term'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'conceptId' => 'Concept Id',
            'term' => 'Término',
        ];
    }

    public static function crearSiNoExiste($codigo, $termino)
    {
        $snoMed = self::findOne(['conceptId' => $codigo]);
        if (!$snoMed) {
            $snoMed = new self();
            $snoMed->conceptId = $codigo;
            
            $snoMed->term = $termino;
            $snoMed->save();
        }
    }    
}
