<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "odonto_nomenclador".
 *
 * @property int $id_odonto_nomenclador
 * @property string|null $codigo_faco
 * @property string|null $detalle_nomenclador
 * @property string|null $categoria_faco
 * @property string|null $tipo_atencion
 */
class OdontoNomenclador extends \yii\db\ActiveRecord
{
    const CUADRANTE_DERECHA_SUPERIOR_DEFINITIVO = [18,17,16,15,14,13,12,11];
    const CUADRANTE_DERECHA_INFERIOR_DEFINITIVO = [48,47,46,45,44,43,42,41];
    const CUADRANTE_IZQUIERDA_SUPERIOR_DEFINITIVO = [21,22,23,24,25,26,27,28];
    const CUADRANTE_IZQUIERDA_INFERIOR_DEFINITIVO = [31,32,33,34,35,36,37,38];

    const CUADRANTE_DERECHA_SUPERIOR_TEMPORAL = [55,54,53,52,51];
    const CUADRANTE_DERECHA_INFERIOR_TEMPORAL = [85,84,83,82,81];
    const CUADRANTE_IZQUIERDA_SUPERIOR_TEMPORAL = [61,62,63,64,65];
    const CUADRANTE_IZQUIERDA_INFERIOR_TEMPORAL = [71,72,73,74,75];

    const CONTRAPARTIDA_TEMPORAL_DEFINITIVA = 
                [55 => 15, 54 => 14, 53 => 13, 52 => 12, 51 => 11, 
                65 => 25, 64 => 24, 63 => 23, 62 => 22, 61 => 21, 
                75 => 35, 74 => 34, 73 => 33, 72 => 32, 71 => 31, 
                85 => 45, 84 => 44, 83 => 43, 82 => 42, 81 => 41];

        
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'odonto_nomenclador';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_odonto_nomenclador'], 'required'],
            [['id_odonto_nomenclador'], 'integer'],
            [['codigo_faco'], 'string', 'max' => 6],
            [['detalle_nomenclador'], 'string', 'max' => 93],
            [['categoria_faco'], 'string', 'max' => 19],
            [['tipo_atencion'], 'string', 'max' => 8],
            [['id_odonto_nomenclador'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_odonto_nomenclador' => 'Id Odonto Nomenclador',
            'codigo_faco' => 'Codigo Faco',
            'detalle_nomenclador' => 'Detalle Nomenclador',
            'categoria_faco' => 'Categoria Faco',
            'tipo_atencion' => 'Tipo Atencion',
        ];
    }
    
    //Busca códigos de atención de acuerdo a su tipo
    public function getOdontoNomenclador($tipo_atencion)
    {
        $odontoNomenclador = OdontoNomenclador::findAll(['tipo_atencion'=>$tipo_atencion]);
        return $odontoNomenclador;
    }

    //Busca códigos de tratamiento por pieza
    public function getOdontoNomencladorPorPieza()
    {
        $odontoNomenclador = OdontoNomenclador::find()->where(['LIKE', 'tipo_atencion', 'pieza'])->all();
        return $odontoNomenclador;
    }

    //Busca códigos de tratamiento de boca completa
    public function getOdontoNomencladorCara()
    {
        $odontoNomenclador = OdontoNomenclador::find()->where(['LIKE', 'tipo_atencion', 'cara'])->all();
        return $odontoNomenclador;
    }

    public function getPiezayCara()
    {
        $odontoNomenclador = OdontoNomenclador::find()
                                    ->where(['LIKE', 'tipo_atencion', 'cara'])
                                    ->orWhere(['LIKE', 'tipo_atencion', 'pieza'])
                                    ->all();
        return $odontoNomenclador;
    }

}
