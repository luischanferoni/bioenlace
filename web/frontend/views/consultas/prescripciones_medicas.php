<?php

use yii\helpers\Html;
use yii\grid\GridView;


$this->title = 'Prescripciones Médicas';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-body">
        <?php // echo $this->render('_search', ['model' => $searchModel]); 
        ?>

        <p>
            <?php //= Html::a('Crear Consulta', ['create'], ['class' => 'btn btn-success']) 
            ?>
        </p>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            //        'filterModel' => $searchModel,
            'headerRowOptions' => ['class' => 'bg-soft-primary'],
            'filterRowOptions' => ['class' => 'bg-white'],
            'pager' => ['class' => 'yii\bootstrap5\LinkPager', 'prevPageLabel' => 'Anterior', 'nextPageLabel' => 'Siguiente', 'options' => ['class' => 'pagination justify-content-center mt-5']],
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                [
                    'attribute' => 'id_consulta',
                    'label' => 'N° Receta',

                ],
                [
                    'attribute' => 'hora',
                    'label' => 'Fecha',
                    'format' => 'raw',
                    'value' => function ($data) {

                        return $data->turno->fecha;
                    }
                ],
                [
                    'attribute' => 'id_consulta',
                    'label' => 'Paciente',
                    'format' => 'raw',
                    'value' => function ($data) {

                        return $data->obtenerPaciente();
                    }
                ],
                [
                    'attribute' => 'id_consulta',
                    'label' => 'Profesional',
                    'format' => 'raw',
                    'value' => function ($data) {

                        return $data->getProfesional($data->turno->id_turnos);
                    }
                ],



                [
                    'attribute' => 'id_consulta',
                    'label' => '',
                    'format' => 'raw',
                    'value' => function ($data) {
                        //return  Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta])." | ".Html::a('Editar', ['consultas/update', 'id' => $data->id_consulta]);
                        return  Html::a('Receta', ['consultas/imprimirreceta', 'id' => $data->id_consulta, 'type' => 'print'], ['class' => 'btn btn-success', 'target' => '_blank']);
                    }
                ],

            ],
        ]); ?>
    </div>
</div>