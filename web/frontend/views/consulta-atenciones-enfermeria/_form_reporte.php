<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Generar Reporte de Atenciones/Controles de Enfermería';
$this->params['breadcrumbs'][] = ['label' => 'Atenciones/Controles', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="generar-reporte">

<div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm-8">
            <div class="card">
                <div class="card-header bg-soft-info">
                    <h2><?= Html::encode($this->title) ?></h2>
                </div>
                <div class="card-body">                
                    <div class="reporte-form">
                        <?php $form = ActiveForm::begin(); ?>
                        <div class="row">
                            <div class="col-xs-6 col-sm-6">
                                <?php
                                
                                $meses = [
                                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                                    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                                    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
                                ];
                                echo Html::label('Mes', 'mes', ['class' => 'control-label']);
                                echo Html::dropDownList('mes', null, $meses, 
                                ['options' => [ date('m') =>['Selected'=>'selected']],
                                'class' => 'form-control','placeHolder'=>'Seleccione un mes']);
                                ?>
                            </div>
                            <div class="col-xs-6 col-sm-6">
                                <?php
                                $annio = date('Y');
                                for ($anio = 2017; $anio < $annio + 1; $anio++) {
                                    $anios[$anio] = $anio;
                                }
                                echo Html::label('Año', 'anio', ['class' => 'control-label']);
                                echo Html::dropDownList('anio', null, $anios, 
                                ['options' => [ $annio =>['Selected'=>'selected']],
                                'class' => 'form-control','placeHolder'=>'Seleccione un año'],
                               
                                );
                                ?>
                            </div>
                        </div>
                        <div class="row form-group text-right mt-3">
                            <div class="col-12">
                                <?= Html::submitButton('Generar Reporte', ['class' => 'btn btn-primary w-100']) ?>
                            </div>                            
                        </div>    
                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-2"></div>
        </div>
    </div>


</div>