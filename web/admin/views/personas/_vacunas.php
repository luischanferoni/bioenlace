<?php

use yii\helpers\Html;
use yii\grid\GridView;

echo GridView::widget([
    'dataProvider' => $vacunas,
    'columns' => [
        ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'sniVacunaNombre',
                'label' => 'Nombre',    
            ], 
            [
                'attribute' => 'nombreGeneralVacuna',
                'label' => 'Tipo',    
            ],
            [
                'attribute' => 'sniVacunaEsquemaNombre',
                'label' => 'Esquema',    
            ],
            [
                'attribute' => 'sniDosisNombre',
                'label' => 'Dosis',    
            ],
        
            [
                'attribute' => 'fechaAplicacion',
                'label' => 'Fecha de Aplicación',    
            ],
            [
                'attribute' => 'origenNombre',
                'label' => 'Efector',    
            ],
        
            [
                'attribute' => 'origenLocalidad',
                'label' => 'Localidad',    
            ],
            [
                'attribute' => 'origenProvincia',
                'label' => 'Provincia',    
            ],
    ],
]);