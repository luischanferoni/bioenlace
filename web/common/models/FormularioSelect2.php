<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;

class FormularioSelect2 extends \yii\base\Model
{
    /**
     * Crea un array de modelos de lo que provenga de un select2
     *
     * @param string $modelClass
     * @param array $modelsToUpdate
     * @return array
     */
    public static function createAndLoad($modelClass, $id = 'id', $modelsToUpdate = [])
    {
        $model = new $modelClass;
        $formName = $model->formName();
        $post     = Yii::$app->request->post($formName);
        $models   = [];

        // array(1) { ["codigo"]=> array(2) { [0]=> string(9) "386438000" [1]=> string(9) "431187006" } }
        // el formato de $post es, 'ConsultaPracticas' => [ 'codigo'=> [ 0 => "386438000", 1 => "431187006" ] ]
        if ($post && is_array($post)) {
            $ok = false;
            $restoAttributes = [];
            foreach ($post as $i => $item) {
                // si es array, puede ser que sea un campo select2
                if (is_array($item)) {
                    $ok = true;
                    // el nombre del attributo se deduce del nombre del campo usado para el select2
                    $attributo_select2 = $i;
                    $attributos = explode("_", $i);
                    if (count($attributos) != 2 || $attributos[0] != 'select2') {
                        return false;
                    }
                    $attributo = $attributos[1];
                    $multiples = $item;
                } else {
                    $restoAttributes[$i] = $item;
                }
            }
            // en el post no hay ninguna atributo de tipo array
            if (!$ok) {
                return false;
            }

            foreach ($multiples as $valor) {
                $model = new $modelClass;                
                $model->{$attributo_select2} = ["-"];
                /*if ($modelClass = 'ConsultaPracticas') {
                    var_dump($valor);die;
                }*/
                $model->{$attributo} = $valor;                
                $model->setAttributes($restoAttributes);
                $models[] = $model;
            }
        }

        unset($model, $formName, $post);
        
        return $models;
    }
}