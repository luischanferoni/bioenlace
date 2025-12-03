<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Persona;
use yii\widgets\Pjax;

$this->title = 'Historial de Antecedentes';
$this->params['breadcrumbs'][] = $this->title;


?>

<div class="consulta-index">

    <h4>Paciente: <span style="font-style: italic"><?= $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) ?></span></h4>
    <?php Pjax::begin(); ?>
    
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'label' => 'Antecedente',
                'value' => function($data) { 
                    return $data->snomedSituacion->term;
                }
            ],                
/*            [
                'attribute' => 'hora',
                'label' => 'Fecha | Hora',
                'value' => function($data) {
                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos'=>$data->id_turnos]);
                    $hora = $consulta_turnos->hora;
                    $fecha = $consulta_turnos->fecha;
                    return $fecha.' | '.$hora;
                }
            ],                    
            [
                'attribute'=> 'id_turnos',
                'label'=> 'Antecedentes',
                'value'=>function($data){
                return common\models\Consulta::getAntecedentesPersona($data->id_turnos);
//                $antecedentes_persona = common\models\Consulta::getAntecedentesPersona($data->id_turnos);
//                $ant_per="";
//                    foreach($antecedentes_persona as $a_p) {
//                        $ant_per .= '-'.$a_p->nombre.'('.$a_p->tipo.')';
//                    }
//                return $ant_per;
                }
            ],*/
                    
//            [
//                'attribute'=> 'id_turnos',
//                'label'=> 'Profesional',
//                'value'=>function($data){
//                return common\models\Consulta::getProfesional($data->id_turnos);
//                }
//            ],
//            [
//                'attribute'=> 'id_turnos',
//                'label'=> 'Efector',
//                'value'=>function($data){
//                return common\models\Consulta::getEfector($data->id_turnos);
//                }
//            ],
            // 'motivo_consulta:ntext',
            // 'observacion:ntext',
            // 'control_embarazo',

//            ['class' => 'yii\grid\ActionColumn'],
/*              [
                 'attribute'=>'id_consulta',
                 'label' => '',
                 'format'=>'raw',
                 'value' => function($data) {
                                    //return  Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta])." | ".Html::a('Editar', ['consultas/update', 'id' => $data->id_consulta]);
                                    return  Html::a('Detalle', ['consultas/view', 'id' => $data->id_consulta]);
                            }
             ],*/
        ],
    ]); ?>
                    <?php Pjax::end(); ?>

</div>
