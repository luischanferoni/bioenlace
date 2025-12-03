<?php

use yii\helpers\Html;
use yii\grid\GridView;

use frontend\controllers\ApiSumarController;

$this->title = 'Consultas Diarias';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]);  ?>

    <p>
        <?php //= Html::a('Crear Consulta', ['create'], ['class' => 'btn btn-success'])  ?>
    </p>

    <?=
    GridView::widget([
        'dataProvider' => $dataProvider,
//        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
//            'id_consulta',
//            'id_turnos',
            [
                'attribute' => 'id_turnos',
                'label' => 'DNI Paciente',
                'value' => function($data) {
                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos' => $data->id_turnos]);
                    $id_persona = $consulta_turnos->id_persona;
                    $model_persona = common\models\Persona::findOne($id_persona);
                    return $model_persona->documento;
                }
            ],
            [
                'attribute' => 'id_turnos',
                'label' => 'Clave Beneficiario',
                'value' => function($data) {
                    $api_sumar = new ApiSumarController();
                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos' => $data->id_turnos]);
                    $id_persona = $consulta_turnos->id_persona;
                    $model_persona = common\models\Persona::findOne($id_persona);
                    if (isset($model_persona->documento)){
                    $param['dni'] = $model_persona->documento;
                    $resultado = $api_sumar->obtener_clavebeneficiario($param);
                    if ($resultado != null) {
                        return $resultado->clave_beneficiario;
                    } else {
                        return 'Debe inscribir la persona al CUS SUMAR';
                    }
                    }
                }
            ],
//            'hora',
            [
                'attribute' => 'fecha',
                'label' => 'Fecha',
                'value' => function($data) {
                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos' => $data->id_turnos]);
                    $fecha = $consulta_turnos->fecha;
                    return $fecha;
                }
            ],
            [
                'attribute' => 'codigo',
                'label' => 'Código',
                'value' => function($data) {
                    $codigo = $data->diagnosticoConsultas->codigo;
                    return $codigo;
                }
            ],
            [
                'attribute' => 'id_consulta',
                'label' => '',
                'format' => 'raw',
                'value' => function($data) {
                    //return  Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta])." | ".Html::a('Editar', ['consultas/update', 'id' => $data->id_consulta]);
                    return Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta]);
                }
            ],
        ],
    ]);
    ?>

</div>
