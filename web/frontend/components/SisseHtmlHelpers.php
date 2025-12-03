<?php

namespace frontend\components;

use Yii;
use yii\base\Component;

class SisseHtmlHelpers extends Component
{
    /**
     * Transforma los valores provenientes de un select multiple a un array de modelos
     * Para identificar que el post trae valores desde un select multiple es obligatorio
     * renombrar el campo agregandole el sufijo select2_ para que este metodo pueda detectarlo
     * el formato de $post es, por ej: 
     * 'ConsultaPracticas' => [ 'codigo'=> [ 0 => "386438000", 1 => "431187006" ] ]
     * 
     * @param string $modelClass
     * @param array $modelsToUpdate
     * @return array
     */
    public static function loadFromSelect2AndCreateModels($modelClass)
    {
        $model = new $modelClass;
        $formName = $model->formName();
        $post     = Yii::$app->request->post($formName);
        $models   = [];

        if ($post && is_array($post)) {
            $postValueSelect2 = null;
            $restoAttributes = [];
            foreach ($post as $postKey => $postValue) {

               /* if (!is_array($postValue)) {
                    $restoAttributes[$postKey] = $postValue;
                    continue;
                }*/

                // tenemos un array, puede que sea un select2
                $attributos = explode("_", $postKey);
                if (count($attributos) != 2 || $attributos[0] != 'select2') {
                    $restoAttributes[$postKey] = $postValue;
                    continue;
                } else {
                    // lo de la derecha es el nombre del atributo
                    // por ej: select2_codigo
                    $attributo = $attributos[1];                  
                    $postValueSelect2 = $postValue;
                }
            }

            if ($postValueSelect2 === null) {
                return false;
            }
            
            // para cuando del post viene vacio select2_codigo => ''
            if (!is_array($postValueSelect2)) {
                return [];
            }
            
            foreach ($postValueSelect2 as $valor) {
                $model = new $modelClass;
                // le tenemos que setear algun valor a este atributo para que 
                // no salte error al momento de validar
                $model->{"select2_".$attributo} = ["-"];
                $model->{$attributo} = $valor;                
                $model->setAttributes($restoAttributes);
                $models[] = $model;
            }
        }

        unset($model, $formName, $post);
        
        return $models;
    }

    /**
     * Lo opuesto al metodo anterior, transforma los valores provenientes de un 
     * grupo de modelos a uno solo 
     * Para identificar que el post trae valores desde un select multiple es obligatorio
     * renombrar el campo agregandole el sufijo select2_ para que este metodo pueda detectarlo
     * el formato de $post es, por ej: 
     * 'ConsultaPracticas' => [ 'codigo'=> [ 0 => "386438000", 1 => "431187006" ] ]
     * 
     * @param string $modelClass
     * @param array $modelsToUpdate
     * @return array
     */
    public static function loadFromModelsAndCreateSelect2($modelClassDestino, $modelosOrigen)
    {
        $modelDestino = new $modelClassDestino;
        if (count($modelosOrigen) == 0) {
            return $modelDestino;    
        }

        $restoAttributes = [];
        $attributo = false;        
        foreach ($modelDestino->activeAttributes() as $fieldName) {            
            $attributos = explode("_", $fieldName);
            if (count($attributos) != 2 || $attributos[0] != 'select2') {
                $restoAttributes[$fieldName] = $modelosOrigen[0]->{$fieldName};
                continue;
            } else {
                // lo de la derecha es el nombre del atributo
                // por ej: select2_codigo
                $attributo = $attributos[1];
            }
        }

        if ($attributo) {
            $arrayValoresSelect2 = [];
            foreach ($modelosOrigen as $modeloOrigen) {
                $arrayValoresSelect2[] = $modeloOrigen->{$attributo};
            }
    
            $modelDestino->{"select2_".$attributo} = $arrayValoresSelect2;
        }
        
        $modelDestino->setAttributes($restoAttributes);
        return $modelDestino;
    }    
}