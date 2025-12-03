<?php

namespace common\models;
use yii\base\model;

class ConsultarValidaciones extends model{
  
 /* public $apellido;
    public $nombre; 
    public $id_tipodoc;
    public $id_estado_civil;
    public $sexo;
    public $fecha_nacimiento;
    public $fecha_defuncion;
    public $fecha_alta;
    public $fecha_mod;
    public $documento;
    public $usuario_alta;
    public $usuario_mod;*/
    
    public function rules()
    {
        return [
                       
            //MODELO PERSONA            
            [['id_tipodoc', 'id_estado_civil', 'sexo'], 'required', 'message'=> 'Campo requerido'], 
            [['id_tipodoc', 'id_estado_civil'], 'integer'],            
            [['sexo'], 'string'],
            [['fecha_nacimiento', 'fecha_defuncion', 'fecha_alta', 'fecha_mod'], 'safe'],     
            [['usuario_alta', 'usuario_mod'], 'string', 'max' => 40],            
            [['apellido','nombre'], 'required', 'message'=> 'Campo requerido'],  
            [['apellido','nombre'], 'string', 'max' => 60],
            ['apellido', 'match','pattern' => '/^[a-z\'\ ñáéíóúü" "]+$/i', 
                'message'=> 'Sólo se aceptan letras(incluidos acentos y diéresis), apóstrofes y espacios'],
            ['nombre', 'match','pattern' => '/^[a-z\'\ ñáéíóúü" "]+$/i', 
                'message'=> 'Sólo se aceptan letras(incluidos acentos y diéresis), apóstrofes y espacios'],         
            [['apellido','nombre'], 'filter', 'filter' => 'trim'],           
            [['documento'], 'required', 'message'=> 'Campo requerido'],                    
            ['documento', 'match','pattern' => '/^[0-9]{5,8}$/i', 'message'=> 'Sólo se permiten digitos,de 5 a 8 caracteres'],
            
            
            //MODELO DOMICILIO
            [['id_localidad'], 'required'],
            [['id_localidad'], 'integer'],
            [['urbano_rural'], 'string'],
            [['fecha_alta'], 'safe'],
            [['calle', 'barrio'], 'string', 'max' => 60],            
            [['calle'], 'string', 'max' => 60],
            [['calle','barrio'], 'required', 'message'=> 'Campo requerido'], 
            ['calle', 'match','pattern' => '/^\D[a-z\d\°\(\)\/\-\ª ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis,digitos(no al comienzo), apóstrofes, símbolos ° ª () / - y espacios'],
            ['barrio', 'match','pattern' => '/^\D[a-z\d\°\(\)\/\-\ª ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis,digitos(no al comienzo), apóstrofes, símbolos ° ª () / - y espacios'],
            [['numero','lote'], 'number', 'message'=> 'Sólo se aceptan digitos'],            
            [['manzana', 'lote', 'torre', 'depto'], 'string', 'max' => 10],
            [['latitud','longitud'], 'double', 'message'=> 'El valor no es válido'],
            [['sector', 'grupo', 'latitud', 'longitud'], 'string', 'max' => 20],
            [['usuario_alta'], 'string', 'max' => 40],
            
            
            //MODELO PERSONA_TELEFONO
            [['numero'], 'required', 'message'=> 'Campo requerido'],
            [['id_tipo_telefono'], 'required', 'message'=> 'Campo requerido'],
            [['id_persona', 'id_tipo_telefono'], 'integer'],
            [['comentario'], 'string'],           
            ['numero', 'match','pattern' => '^\+?\(?\d{2,4}\)?[\d\s-]{3,}$','message'=> 'El formato no es válido'],
            
            //MODELO PROGRAMA
            
            [['nombre','referente'], 'required', 'message'=> 'Campo requerido'],
            [['nombre'], 'unique'],
            ['nombre', 'match','pattern' => '/^[a-z\d\'\ ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis), digitos, apóstrofes, y espacios'],
            ['referente', 'match','pattern' => '/^[a-z\'\ ñáéíóúü" "]+$/i', 
               'message'=> 'Sólo se aceptan letras(incluidos acentos y diéresis), apóstrofes y espacios'], 
            
            //MODELO LOCALIDAD
            [['nombre', 'cod_postal', 'id_departamento','id_provincia'], 'required','message'=> 'Campo requerido'],       
            [['id_localidad', 'id_departamento'], 'integer'],           
            ['nombre', 'match','pattern' => '/^[a-z\d\'\.\ ñáéíóúü\s]+$/i', 
               'message'=> 'Sólo se aceptan letras(incluidos acentos y diéresis), apóstrofes, puntos, digitos y espacios'],          
            ['cod_postal', 'match','pattern' => '/^[0-9]{4}\s*$|^[a-h,j-z,A-H,J-Z][0-9]{4}[a-zA-Z]{3}\s*$/i',
                'message'=> 'Sólo se aceptan los dos formatos válidos para Argentina'],
            [['cod_postal'], 'unique'],
            
            //MODELO PERSONAS_MAILS
            ['mail', 'email','message'=> 'Formato inválido'],
           
            //MODELO ESPECIALIDAD
            ['nombre', 'match','pattern' => '/^[a-z\d\'\ ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis), digitos, apóstrofes, y espacios'],
            [['nombre'], 'filter', 'filter' => 'trim'], 
            [['nombre'], 'unique'], 
            
            
            //MODELO PROFESION
            ['nombre', 'match','pattern' => '/^[a-z\d\'\ ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis), digitos, apóstrofes, y espacios'],
            [['nombre'], 'filter', 'filter' => 'trim'], 
            [['nombre'], 'unique'], 
            
            //MODELO USER 
             ['username', 'match','pattern' => '/^[a-z\d\_\ñÑ\s]+$/i', 
                'message'=> 'Sólo se aceptan letras (incluidas la ñ) , digitos,espacios y el símbolo _'], 
             ['password_hash', 'match','pattern' => '/^[a-z\d\_\s]+$/i', 
                'message'=> 'Sólo se aceptan letras, digitos,espacios y el símbolo _'],
             ['email', 'email','message'=> 'Formato inválido'],
            
            //MODELO SERVICIO
            ['nombre', 'match','pattern' => '/^[a-z\d\'\ ñáéíóúü\s]+$/i','message'=> 'Sólo se aceptan letras'
                . '(incluidos acentos y diéresis), digitos, apóstrofes, y espacios'],
            [['nombre'], 'filter', 'filter' => 'trim'], 
            [['nombre'], 'unique'], 
            
            //PARA VALIDAR LA HORA
             ['hora', 'match','pattern' => '/^(((0[0-9])|(1[0-9])|(2[0-3])):[0-5][0-9])$/i', 
                'message'=> 'El formato válido es: "hh:mm"(formato de 24 horas)'],
           
        ];
    }

    public function attributeLabels()
    {
        return [
            
        ];
    }

}


