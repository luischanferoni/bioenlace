<?php

namespace frontend\modules\mapear\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\filters\auth\HttpBearerAuth;
use yii\helpers\Url;
use frontend\modules\mapear\models\Regla;
use frontend\modules\mapear\models\ConceptoDestino;

use sizeg\jwt\Jwt;

class SiteController extends \yii\rest\Controller
{
  /**
     * @inheritdoc
     */
  /*  public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::className(),
        ];
        $behaviors['contentNegotiator'] = [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'application/json' => yii\web\Response::FORMAT_JSON,
            ],
        ];

        return $behaviors;
    }*/

    public function actionPrueba()
    {
        return "OK";
    }

    /**
     * 
     * Cada parametro que se recibe representa con conceptId
     * 
     * @param string ambito
     * @param string especialidad
     * @param string procedimiento
     * @param string diagnostico
     * @param string tipo ('sumar', 'pmo', 'medicacion')
     */
    public function actionMapear()
    {

        $limit = 1;
        $reglas_finales = [];
        $reglas_diagnostico = [];

        $valid = $this->conceptIdParamCheck(Yii::$app->request->post());
        $cumpleRegla = [];
        if (!$valid[0]) {
            throw new BadRequestHttpException($valid[1]);
        }
        // tipo 
        $tipo = Yii::$app->request->post('tipo');

        // trae todas las reglas segun el tipo
        
        $ambito = Yii::$app->request->post('ambito');

        $especialidad = Yii::$app->request->post('especialidad');
 
        
        $diagnosticoProcedimientos = Yii::$app->request->post('diagnosticoProcedimientos');


        // La relacion es regla->condicion->ecl_snomed
/*

        $reglas_finales = [["id"=>1,"tipo"=>"sumar","id_destino"=>85],["id"=>49,"tipo"=>"sumar","id_destino"=>13]];

        $conceptos_destino = ArrayHelper::getColumn($reglas_finales, 'id_destino');

        $conceptos = ConceptoDestino::find()->where(['in','id',$conceptos_destino])->all();

        return ['conceptos' => $conceptos];
        
    */
 
        $cantidad_reglas = Regla::find()->where(['tipo' => $tipo])->count();
        $reglas_finales = [];


        //for ($offset=0; $offset < $cantidad_reglas; $offset+=25) { 
            
        $reglas = Regla::find()->where(['tipo' => $tipo])->all();
        // recorre array de diagnosticos y procedimientos 
        
        foreach ($diagnosticoProcedimientos as $diagnostico => $procedimientos) {
            foreach($reglas as $regla) {                
                $condiciones = $regla->condicion;
                $array_resultados = [];
                foreach($condiciones as $condicion) {                    
                    if($condicion->ecl->categoria == 'diagnostico') {
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($diagnostico, $condicion->ecl->ecl, [], $limit);                        
                        if(isset($resultados['items']) && count($resultados['items']) > 0) {
                            $reglas_diagnostico[$diagnostico][] = $regla;                               
                        } /*else {
                            if(!isset($resultados['items']) ){
                                
                            }
                        }*/
                    }
                }              
            }

            foreach ($procedimientos as $procedimiento) {
                # code...
                if(isset($reglas_diagnostico[$diagnostico]) && count($reglas_diagnostico[$diagnostico]) > 0) {
                    foreach ($reglas_diagnostico[$diagnostico] as $regla) {
                        # code...
                        $condiciones = $regla->condicion;
                        foreach($condiciones as $condicion) {
                            if($condicion->ecl->categoria == 'procedimiento') {
                                $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($procedimiento, $condicion->ecl->ecl, $limit); 
                               // $ecl[] = ['concepto' => $procedimiento, 'ecl' => $condicion->ecl->ecl];                                     
                                if(isset($resultados['items']) && count($resultados['items']) > 0) {
                                    $reglas_finales[] = $regla;                               
                                }                   
                            }
                        }        
                    }
                }
            }           
        }

        // Filtro reglas que cumplan la condicion del ambito
        foreach($reglas_finales as $key => $regla) {
            $condiciones = $regla->condicion;           
            foreach($condiciones as $condicion) {
                if($condicion->ecl->categoria == 'ambito') {
                    $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($ambito, $condicion->ecl->ecl, [], $limit);                   
                    if(isset($resultados['items']) && count($resultados['items']) == 0) {
                        unset($reglas_finales[$key]);
                    }                   
                }
            }
        }
         
      
        //Recorre las reglas que cumplen y filtro por especialidad, descartando las que no cumplen la condicion
        foreach($reglas_finales as $key => $regla) {         
                $condiciones = $regla->condicion;
                foreach($condiciones as $condicion) {
                    if($condicion->ecl->categoria == 'especialidad') {
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($especialidad, $condicion->ecl->ecl, [], $limit);  
                        if(isset($resultados['items']) && count($resultados['items']) == 0) {
                            unset($reglas_finales[$key]);
                        }                   
                    }                
            }
        }
       
      //  sleep(1);
    //}

    $conceptos_destino = ArrayHelper::getColumn($reglas_finales, 'id_destino');
    $conceptos = ConceptoDestino::find()->where(['in', 'id', $conceptos_destino])->all();
    return ['conceptos' => $conceptos];

       // return $reglas_diagnostico;
        //return $reglas_finales;



       // return $reglas_finales;
        /*foreach($reglas as $regla) {
            $condiciones = $regla->condicion;
            $noCumple = false;
            $cantidad = 0;
            foreach($condiciones as $condicion) {
                switch ($condicion->ecl->categoria) {
                    case 'ambito':
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($ambito, $condicion->ecl->ecl, $limit); 
                        // echo $condicion->ecl->id.' - '.$condicion->ecl->ecl;
                    //    var_dump($resultados);  
                        if(!isset($resultados['items']) || count($resultados['items']) == 0) {
                            $noCumple = true;
                        } else {
                            $cantidad++;
                        }
                        break;
                    case 'especialidad':
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl($especialidad, $condicion->ecl->ecl, $limit); 
                                               
                        if(!isset($resultados['items']) || count($resultados['items']) == 0) {
                        //     echo $condicion->ecl->id.' - '.$condicion->ecl->ecl;
                        // var_dump($resultados); 
                            $noCumple = true;
                        } else {
                            $cantidad++;
                        }            
                        break;
                    case 'procedimiento':
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl("", $condicion->ecl->ecl, $procedimientos);
                        // echo $condicion->ecl->id.' - '.$condicion->ecl->ecl;
                         //var_dump($resultados);                  
                        if(!isset($resultados['items']) || count($resultados['items']) == 0) {
                            $noCumple = true;
                        } else {
                            $itemsProcedimientos = $resultados['items'];
                            $cantidad++;
                        }
                        break;
                    case 'diagnostico':
                        $resultados = Yii::$app->snowstorm->busquedaFiltradaEcl("", $condicion->ecl->ecl, $diagnosticos);     
                        // echo $condicion->ecl->id.' - '.$condicion->ecl->ecl;
                        ($resultados);              
                        if(!isset($resultados['items']) || count($resultados['items']) == 0) {
                            $noCumple = true;
                        } else {
                            $itemsDiagnosticos = $resultados['items'];
                            $cantidad++;
                        }
                        break;                  
                }
                if ($noCumple) {
                    # Si no cumple alguna de las condiciones salta la regla...
                    break;
                }                  
            }
            if($noCumple == false && $cantidad == 4) {
                $cumpleRegla[] = [$regla->id, $regla->conceptoDestino->codigo, $regla->conceptoDestino->concepto];
                $cantidad = 0;
            }
        }*/

 
    }

    private function conceptIdParamCheck($post)
    {

        if (!is_numeric($post['ambito']) || strlen($post['ambito']) < 6 || strlen($post['ambito']) > 18) {
            return [false, 'Error en el ambito: '.$post['ambito']];
        }

        if (!is_numeric($post['especialidad']) || strlen($post['especialidad']) < 6 || strlen($post['especialidad']) > 18) {
            return [false, 'Error en el especialidad: '.$post['especialidad']];
        }
        
        if (!is_array($post['diagnosticoProcedimientos'])) {
            return [false, 'Error en el listado de diagnosticos - procedimientos: '.$post['diagnosticoProcedimientos']];
        } 
        
        return [true];
    }
}