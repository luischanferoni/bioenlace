<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "consultas_receta_lentes".
 *
 * @property int $id
 * @property float|null $oi_esfera
 * @property float|null $od_esfera
 * @property float|null $oi_cilindro
 * @property float|null $od_cilindro
 * @property float|null $oi_eje
 * @property float|null $od_eje
 */
class ConsultasRecetaLentes extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'consultas_receta_lentes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id','id_consulta'], 'integer'],
            [['oi_esfera', 'od_esfera', 'oi_cilindro', 'od_cilindro', 'oi_eje', 'od_eje','od_add','oi_add'], 'number'],
            [['oi_esfera', 'od_esfera', 'oi_cilindro', 'od_cilindro'], 'validateDivisible'],
            [['od_add','oi_add'],'validateDivisiblePosititvo'],
            [['oi_eje', 'od_eje'], 'validateMayormenor'],
        ];
    }

    public function validateDivisible($attribute, $params, $validator){
        $division = $this->$attribute / 0.25;
        $division = intval($division);
        $resto = $this->$attribute - ($division * 0.25);
        if($resto != 0):
            $validator->addError($this, $attribute, 'Debe ser divisible por 0.25');
        endif;
    }

    public function validateDivisiblePosititvo($attribute, $params, $validator){

        if($this->$attribute < 0):
            $validator->addError($this, $attribute, 'Debe ser mayor que 0');
        endif;

        $division = $this->$attribute / 0.25;
        $division = intval($division);
        $resto = $this->$attribute - ($division * 0.25);
       
        if($resto != 0):
            $validator->addError($this, $attribute, 'Debe ser divisible por 0.25');
        endif;
       

    }

    public function validateMayormenor($attribute, $params, $validator){

        if($this->$attribute < 0 or $this->$attribute > 180):
            $validator->addError($this, $attribute, 'Este valor debe estar entre 0 y 180');
        endif;
        if($attribute == 'oi_eje'):
            if($this->oi_cilindro == ''):
                $validator->addError($this, 'oi_cilindro', 'Se debe cargar Cilindro Ojo Izquierdo!');
            endif;
        endif;
        if($attribute == 'od_eje'):
            if($this->od_cilindro == ''):
                $validator->addError($this, 'od_cilindro', 'Se debe cargar Cilindro Ojo Izquierdo!');
            endif;
        endif;
    }

    /**
     * Retorna los campos requeridos en lenguaje natural para prompts de IA
     * @return array
     */
    public function requeridosPrompt()
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'oi_esfera' => 'Oi Esfera',
            'od_esfera' => 'Od Esfera',
            'oi_cilindro' => 'Oi Cilindro',
            'od_cilindro' => 'Od Cilindro',
            'oi_eje' => 'Oi Eje',
            'od_eje' => 'Od Eje',
            'id_consulta' => 'Id Consulta'
        ];
    }
}
