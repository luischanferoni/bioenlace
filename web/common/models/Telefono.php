<?php

namespace common\models;

use Yii;
use yii\base\Model;

/**
 * ContactForm is the model behind the contact form.
 */
class Telefono extends Model
{
    const PREFIJO = ['+54'=>'Argentina','+34'=>'España','+55'=>'Brasil','+86'=>'China','+52'=>'Mexico','+57'=>'Colombia',
    '+39'=>'Italia','+591'=>'Bolivia','+56'=>'Chile','+593'=>'Ecuador','+1'=>'Estados Unidos','+33'=>'Francia','+51'=>'Peru','+598'=>'Uruguay','+595'=>'Paraguay'];

    const VALIDAR_TELEFONO = 'validartel';

    public $codArea;
    public $numTelefono;
    public $prefijo;
    

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            ['codArea', 'string', 'length' => [2, 4],'message'=>'Ingrese un Codigo de Area correcto (Sin el 0)'],
            ['numTelefono','string','length'=>[7,9],'message'=>'Ingrese un numero de telefono valido'],
            ['codArea', 'match', 'pattern' => '/^[1-9][0-9]+$/','message'=>'Ingrese un Codigo de Area correcto (Sin el 0)'],
            ['numTelefono', 'match', 'pattern' => '/^[0-9]+$/','message'=>'Ingrese un numero de telefono valido'],
            ['prefijo','safe'],
            [['codArea','numTelefono'], 'required','on'=> self::VALIDAR_TELEFONO]
    
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'prefijo'=>'Pais',
            'codArea'=>'Cod. De Area',
            'numTelefono'=>'Numero de Telefono'
            
        ];
    }

}
