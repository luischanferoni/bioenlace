<?php

namespace common\models\snomed;

use Yii;

/**
 * This is the model class for table "snomed_procedimientos".
 *
 * @property integer $conceptId
 * @property string $term
 */
class SnomedProcedimientos extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'snomed_procedimientos';
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


    public static function getTerm($codigo){

        $practica = self::findOne(['conceptId' => $codigo]);
        $term = "";

        if (isset($practica)){
            $term = $practica->term;
        }

        return $term;

    }

}
