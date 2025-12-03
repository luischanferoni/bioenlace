<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */


$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['buscar-persona']];
$this->params['breadcrumbs'][] = $this->title;
//print_r($dataProvider);
?>
<div class="buscar-persona">
    
    <?php echo $this->render('_set_minimo',['model' => $model]); ?>   
    <?php echo $this->render('_set_ampliado',['model' => $model,
        'id' => $id,
        'tipo' => $tipo,  
        'score' => $score,   
                    'score' => $score, 
                    'model_domicilio' => $model_domicilio,
                    'model_localidad' => $model_localidad,
                    'model_provincia' => $model_provincia,
                    'model_departamento' => $model_departamento,
                    'model_persona_telefono' => $model_persona_telefono,
                    'model_persona_mails' => $model_persona_mails,
                    'model_tipo_telefono' => $model_tipo_telefono]); ?>   
</div>
