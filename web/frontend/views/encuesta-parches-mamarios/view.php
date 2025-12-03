<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

use common\models\Persona;


/* @var $this yii\web\View */
/* @var $model common\models\EncuestaParchesMamarios */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Encuesta Parches Mamarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="encuesta-parches-mamarios-view">

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            [
                'attribute' => 'id_operador',
                'label' => 'Operador',
                'value' => $model->operador->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),    
            ],            
            'fecha_prueba',
            'numero_serie',
            [
                'attribute' => 'id_efector',
                'label' => 'Efector',
                'value' =>   $model->created_at < '2024-03-28 00:00:00' ? $model->efector->nombre: $model->operador->efector->nombre 
            ],
            'antecedente_cancer_mama',
            'antecedente_cirugia_mamaria',
            'actualmente_amamantando',
            'sintomas_enfermedad_mamaria',
            'edad_primer_periodo',
            'tiene_hijos',
            'edad_primer_parto',
            'paso_menospausia',
            'edad_menospausia',
            'terapia_remplazo_hormonal',
            'senos_densos',
            'biopsia_mamaria',
            'fecha_biopsia',
            'resultado_biopsia',
            'antecedente_familiar_cancer_mamario_ovarico',
            'consume_alcohol',
            'consume_tabaco',
            'mamografia',
            'fecha_ultima_mamografia',
            'prueba_adicional',
            'prueba_adicional_tipo',
            'a_izquierdo',
            'a_derecho',
            'a_diferencia',
            'b_izquierdo',
            'b_derecho',
            'b_diferencia',
            'c_izquierdo',
            'c_derecho',
            'c_diferencia',
            'observaciones:ntext',
            'resultado',
            'resultado_indicado',
        ],
    ]) ?>

</div>
