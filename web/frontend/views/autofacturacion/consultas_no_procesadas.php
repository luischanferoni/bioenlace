<?php

use yii\grid\GridView;
use common\models\Persona;
use kartik\daterange\DateRangePicker;
use yii\bootstrap5\ActiveForm;

$this->title = 'Consultas No Procesadas';

$form = ActiveForm::begin();

?>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <h4 class="card-title">Listado de Consultas No Procesadas</h4>
            </div>
        </div>

        <div class="card-body">


            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'summary' => '',
                'options' => ['class' => 'table-responsive'],
                'tableOptions' => ['class' => 'table table-striped table-hover table-bordered rounded'],
                'headerRowOptions' => ['class' => 'bg-primary text-white'],
                'filterRowOptions' => ['class' => 'bg-white'],
                'columns' => [
                    [
                        'label' => 'Fecha de Consulta',
                        'value' => function ($data) {
                            return Yii::$app->formatter->asDate($data->created_at, 'dd/MM/yyyy');
                        }
                    ],
                    [
                        'label' => 'Paciente',
                        'value' => function ($data) {
                            return $data->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
                        }
                    ],
                    [
                        'label' => 'Profesional de Salud',
                        'contentOptions' => ['class' => 'text-wrap'],
                        'value' => function ($data) {                            
                            return isset($data->rrhhEfector) ? $data->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : '';
                        }
                    ],
                ]
            ]);
            ?>
        </div>
    </div>
</div>