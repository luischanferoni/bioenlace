<?php

namespace common\models\snomed;

use Yii;

/**
 * This is the model class for table "snomed_hallazgos".
 *
 * @property integer $conceptId
 * @property string $term
 */
class SnomedHallazgos extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'snomed_hallazgos';
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
            
            $snoMed->term = $termino;            ;
            if (!$snoMed->save()) {
                throw new \Exception();
            }
        }
        return $snoMed;
    }

    public function validarDiagnosticosActivos($diagnosticosActivos) {

        foreach ($diagnosticosActivos as $key => $diagnosticoActivo) {
            # TODO: verificar que los codigos recibidos tengan su correspondiente en la tabla
            # snomed_hallazgos
            $diagnosticoActivo->codigo;
        }

    }
}
