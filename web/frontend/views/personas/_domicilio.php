<?php
use yii\grid\GridView;

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        [
            'label' => 'Calle y Nro',
            'value' => function($data) { 
                $datos = '-';
                
                if (isset($data->domicilio->calle) && $data->domicilio->calle != ""){                
                    $datos = $data->domicilio->calle." ".$data->domicilio->numero;
                }

                return $datos;
            }
        ],
        [
            'label' => 'Otros datos',
            'value' => function($data) { 
                $datos = " - ";
               if (isset($data->domicilio->manzana) && $data->domicilio->manzana != "") {
                $datos = "MZA: ".$data->domicilio->manzana;
               }
               if (isset($data->domicilio->lote) && $data->domicilio->lote != "") {
                $datos .= " Lote: ".$data->domicilio->lote;
               }
               if (isset($data->domicilio->sector) && $data->domicilio->sector != "") {
                $datos .= " Sector: ".$data->domicilio->sector;
               }
               if (isset($data->domicilio->grupo) && $data->domicilio->grupo != "") {
                $datos .= " Grupo: ".$data->domicilio->grupo;
               }
               if (isset($data->domicilio->torre) && $data->domicilio->torre != "") {
                $datos .= " Torre: ".$data->domicilio->torre;
               }
               if (isset($data->domicilio->piso) && $data->domicilio->piso != "") {
                $datos .= " Piso: ".$data->domicilio->piso;
               }
               if (isset($data->domicilio->depto) && $data->domicilio->depto != "") {
                $datos .= " Dpto: ".$data->domicilio->depto;
               }

               return $datos;               
            }
        ],
        [
            'label' => 'Entre calles',
            'value' => function($data) {
                $datos = " - ";

                if (isset($data->domicilio->entre_calle_1) && $data->domicilio->entre_calle_1 != "") {
                    $datos = $data->domicilio->entre_calle_1;
                }
                if (isset($data->domicilio->entre_calle_2) && $data->domicilio->entre_calle_2 != "") {
                    $datos = " y ".$data->domicilio->entre_calle_2;
                }
                return $datos;
            }
        ],
        [
            'label' => 'Barrio',
            'value' => function($data) { 
                return is_numeric($data->domicilio->barrio)?$data->domicilio->modelBarrio->nombre:$data->domicilio->barrio;
            }
        ],
        [
            'label' => 'Localidad',
            'value' => function($data) { 
                return $data->domicilio->localidad->nombre;
            }
        ],
        'activo'        
        
        
    ],
]);
