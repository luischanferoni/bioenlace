<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;

class FormularioDinamico extends \yii\base\Model
{
    /**
     * Creates and populates a set of models.
     *
     * @param string $modelClass
     * @param array $modelsToUpdate
     * @return array
     */
    public static function  createAndLoadMultiple($modelClass, $id = 'id', $modelsToUpdate = [])
    {
        $model    = new $modelClass;
        $formName = $model->formName();
        $post     = Yii::$app->request->post($formName);
        $models   = [];

        if (! empty($modelsToUpdate)) {
            $keys = array_keys(ArrayHelper::map($modelsToUpdate, $id, $id));

            // if(count($modelsToUpdate) != count($keys)){
            //     echo "<pre>";
            //     print_r($modelsToUpdate);
            //     echo "</pre><br>";
            //     echo "<br>";
            //     echo "<br>";
            //     print_r($keys); die;
            // }
            if(count($modelsToUpdate) == count($keys)) {
                $modelsToUpdate = array_combine($keys, $modelsToUpdate);
            }            
            
        }

        // el formato de $post es, 'ConsultaPracticas' => [0 => ['id' => .., ...], 1 => ...]
        if ($post && is_array($post)) {
            foreach ($post as $i => $item) {
                // si recibimos el id en el post es porque es un update, 
                // en teoria en el array el 'id' viene al comienzo 
                // Consulta => ['id o id_consulta' => ..., ... resto de parametros]
                if (isset($item[$id]) && !empty($item[$id]) && isset($modelsToUpdate[$item[$id]])) {
                    $models[] = $modelsToUpdate[$item[$id]];
                } else {              
                    $model = new $modelClass;
                    $models[] = $model;
                }
            }
        }

        $modelClass::loadMultiple($models, Yii::$app->request->post(), $formName);
        unset($model, $formName, $post);
        
        return $models;
    }
    
    /**
     * Creates and populates a set of models.
     *
     * @param string $modelClass
     * @param array $multipleModels
     * @return array
     */
    
    public static function createMultiple($modelClass, $multipleModels = [])
    {
        $model    = new $modelClass;
        $formName = $model->formName();
        $post     = Yii::$app->request->post($formName);
        $models   = [];

        if (! empty($multipleModels)) {
            $keys = array_keys(ArrayHelper::map($multipleModels, 'codigo', 'codigo'));
            $multipleModels = array_combine($keys, $multipleModels);
        }

        if ($post && is_array($post)) {
            foreach ($post as $i => $item) {
                if (isset($item['id']) && !empty($item['id']) && isset($multipleModels[$item['id']])) {
                    $models[] = $multipleModels[$item['id']];
                } else {
                    $models[] = new $modelClass;
                }
            }
        }

        unset($model, $formName, $post);

        return $models;
    }
    
}
