<?php

namespace common\models\snomed;

use Yii;

/**
 * La clase padre de todos los modelos snomed
 *
 */
abstract class Snomed extends \yii\db\ActiveRecord
{   
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