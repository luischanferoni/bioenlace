<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\persona_telefono;
use common\models\Tipo_telefono;
use common\models\Localidad;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\persona */

//$this->title = $model->id_persona; 

$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-view">
    <div class="row">
        <div class="col-sm">
            <h3><?= Html::encode($this->title) ?></h3>
        </div>
    </div>
    <?php
    Yii::$app->formatter->locale = 'es-Es';
    $Array_sexo = ['1' => 'Femenino', '2' => 'Masculino', 'F' => 'Femenino', 'M' => 'Masculino', '' => 'Indefinido', '3' => 'Otro', '4' => 'Indefinido'];
    ?>

    <div class="card mt-5">

        <div class="card-header bg-info">
            <h4 class="text-white">Datos Personales de: <?=$model->apellido?>, <?=$model->nombre?> </h4>
        </div>
        
        <div class="card-body">
            <div class="table-responsive mt-4 border rounded">
                <?= DetailView::widget([
                    'model' => $model,
                    'options' => ['class' => 'table table-striped mb-0'],
                    'attributes' => [
                        [
                            'label' => 'Apellidos',
                            'value' => $model->apellido . ' ' . $model->otro_apellido,
                        ],

                        [
                            'label' => 'Nombres',
                            'value' => $model->nombre . ' ' . $model->otro_nombre,
                        ],
                        [
                            'label' => 'N° Documento ',
                            'value' => $model->documento,
                            // $model->tipoDocumento->nombre .' '. 
                        ],
                        [
                            'label' => 'Fecha de Nacimiento',
                            'value' => date('d/m/Y', strtotime($model->fecha_nacimiento)),
                        ],
                        [
                            'label' => 'Sexo Biologico',
                            'value' => $Array_sexo[$model->sexo_biologico],
                        ],
                    ],

                ]);
                ?>
            </div>
        </div>
    </div>

</div>