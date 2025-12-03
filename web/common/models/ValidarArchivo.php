<?php
 
namespace common\models;
use yii\base\model;
 
class ValidarArchivo extends model{
  
    public $file;
     
    public function rules()
    {
        return [
            ['file', 'file',
                'skipOnEmpty' => false,
                'uploadRequired' => 'No has seleccionado ningún archivo', //Mensaje de error
                'maxSize' => 1024 * 1024 * 1, //Tamaño máximo del archivo ->1 MB 
                'tooBig' => 'El tamaño máximo permitido es 1MB', //Mensaje de error
                'minSize' => 10, //Tamaño máximo del archivo ->10 Bytes
                'tooSmall' => 'El tamaño mínimo permitido son 10 BYTES', //Mensaje de error
                'extensions' => 'xls, xlsx',  //Tipo de extensiones permitidas separadas por ,
                'wrongExtension' => 'El archivo {file} no contiene una extensión permitida ({extensions})', //Mensaje de error
                'maxFiles' => 1,   //N° de archivos permitidos para subir
                'tooMany' => 'El máximo de archivos permitidos son {limit}', //Mensaje de error
            ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'file' => 'Seleccione el archivo correspondiente a la tabla efectores de SISA:',
        ];
    }

}


