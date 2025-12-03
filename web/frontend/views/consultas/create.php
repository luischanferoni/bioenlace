<?php

use yii\widgets\ActiveForm;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\bootstrap5\Modal;
use yii\bootstrap5\Dropdown;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */

$this->title = 'Crear Consulta';
$this->params['breadcrumbs'][] = ['label' => 'Consultas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<?php
$h = 'Datos de la consulta (Adulto)';
if (Yii::$app->getRequest()->getQueryParam('tipo_cons')) {
    switch (Yii::$app->getRequest()->getQueryParam('tipo_cons')) {
        case 1:
            $h = 'Datos de la consulta (Menor de 1 año)';
            $subform = '_form_menoresdeunanio';
            $tipo_consulta = 1;
            break;
        case 3:
            $h = 'Datos de la consulta (Adolescente)';
            $subform = '_form_adolescente';
            $tipo_consulta = 3;
            break;
        case 4:
            $h = 'Datos de la consulta (Embarazada)';
            $subform = '_form_embarazada';
            $tipo_consulta = 4;
            break;
        case 5:
            $h = 'Datos de la consulta (Puerpera)';
            $subform = '_form_puerpera';
            $tipo_consulta = 5;
            break;
        case 6:
            $h = 'Datos de la consulta (Adulto)';
            $subform = '_form_adulto';
            $tipo_consulta = 6;
            break;
    }
} else {
    $edad = $model_persona->edad;
    if ($edad < 1) {
        $h = 'Datos de la consulta (Menor de 1 año)';
        $subform = '_form_menoresdeunanio';
        $tipo_consulta = 1;
    }
    if ($edad >= 1 and $edad <= 12) {
        $h = 'Datos de la consulta (Niños de 1 a 12 años)';
        $subform = '_form_niniosdeunoadoce';
        $tipo_consulta = 2;
    }
    if ($edad > 12 and $edad <= 19) {
        $h1 = 'Datos de la consulta (Adolescente)';
        $subform = '_form_adolescente';
        $tipo_consulta = 3;
    }
    if ($edad > 19) {
        $h = 'Datos de la consulta (Adulto)';
        $subform = '_form_adulto';
        $tipo_consulta = 6;
    }
}

$array_opciones = [
    [
        'label' => 'Cargar datos',
        'url' => ['atenciones-enfermeria/create', 'id_persona' => $id_persona, 'id_rr_hh' => $id_rr_hh],
        'linkOptions' => [
            'class' => 'modalGeneral',
            'data-title' => 'Historial de Consultas',
        ]
    ],
];
?>
        
        <?php
        switch ($model->parent_class) {
            case 'Turno':
                
                echo $this->render('_form', [
                    'subform' => $subform,
                    'model' => $model,
                    'modelMotivosConsulta' => $modelMotivosConsulta,
                    'modelosConsultaDiagnostico' => $modelosConsultaDiagnostico,
                    'modelConsultaSintomas' => $modelConsultaSintomas,
                    'model_medicamentos_consulta' => $model_medicamentos_consulta,

                    'servicio' => $servicio,
                    
                    'modelOdontoConsultaPersona' => $modelOdontoConsultaPersona,
/*                    'model_odonto_nomenclador' => $model_odonto_nomenclador,
                    'model_odonto_nomenclador_por_pieza' => $model_odonto_nomenclador_por_pieza,
                    'model_odonto_nomenclador_completa' => $model_odonto_nomenclador_completa,
                    'model_odonto_nomenclador_caras' => $model_odonto_nomenclador_caras,*/

                    'dataNomencladorConsulta' => $dataNomencladorConsulta,
                    'dataNomencladorPorPieza' => $dataNomencladorPorPieza,
                    'dataNomencladorTto' => $dataNomencladorTto,
                    'dataNomencladorCaras' =>  $dataNomencladorCaras,
                    'odontogramaPaciente' => $odontogramaPaciente,
                    'odontograma_paciente_caras_pieza_dental' => $odontograma_paciente_caras_pieza_dental,
                    'indice' => $indice,
                    'grafico' => $grafico,
                    'historial' =>  $historial,
                  
                    'modelConsultaPracticas' => $modelConsultaPracticas,
                    'modelConsultaPracticasSolicitadas' => $modelConsultaPracticasSolicitadas,
                    'model_personas_antecedente' => $model_personas_antecedente,
                    'model_personas_antecedente_2' => $model_personas_antecedente_2,
                    //---------
                    'valores_enfermeria' => $valores_enfermeria,
                    //---------
                    //'model_persona' => $model_persona,
                    'model_turno' => $model_turno,
                    'model_alergias' => $model_alergias,
                    'id_turno' => $model_turno->attributes['id_turnos'],
                    'id_persona' => $id_persona,
                    'tipo_consulta' => $tipo_consulta,
                    'idConsulta' => $idConsulta,
                ]);
                break;

            case 'SegNivelInternacion':
                echo $this->render('_form', [
                    'subform' => $subform,
                    'model' => $model,
                    'modelosConsultaDiagnostico' => $modelosConsultaDiagnostico,
                    'modelConsultaSintomas' => $modelConsultaSintomas,
                    'model_medicamentos_consulta' => $model_medicamentos_consulta,
                    'modelConsultaPracticas' => $modelConsultaPracticas,
                    'modelConsultaPracticasSolicitadas' => $modelConsultaPracticasSolicitadas,
                    'model_personas_antecedente' => $model_personas_antecedente,
                    'model_personas_antecedente_2' => $model_personas_antecedente_2,
                    'model_embarazo' => $model_embarazo,
                    'valores_enfermeria' => $valores_enfermeria,
                    'model_internacion' => $model_internacion,
                    'model_alergias' => $model_alergias,
                    'tipo_consulta' => $tipo_consulta,
                ]);
                break;
        }
        ?>
<?php
Modal::begin([
    'title' => '<h1>Historial de Consultas</h1>',
    'id' => 'modal',
    'size' => 'modal-lg',
]);
echo "<div id='modalContent'></div>";
Modal::end();
?>            


<?php
Modal::begin([
    'title' => '<h1>Historial de Antecedentes</h1>',
    'id' => 'modalAntec',
    'size' => 'modal-lg',
]);
echo "<div id='modalContentAntec'></div>";
Modal::end();
?>   