<?php
use kartik\form\ActiveForm;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
//use yii\widgets\ActiveForm;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use common\models\Efector;
use common\models\Tipo_dia;
use common\models\Persona;
use common\models\RrhhEfector;
use kartik\select2\Select2;
use kartik\checkbox\CheckboxX;


/* @var $this yii\web\View */
/* @var $model common\models\Agenda_rrhh */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="agenda-rrhh-form">

    <?php $form = ActiveForm::begin(); ?>
    <div class="form-group">
        <div class="col-md-10 columns">
            <?php if($model->isNewRecord){ 
            echo $form->field($model, 'id_rr_hh')->widget(Select2::classname(), [
                'data' => ArrayHelper::map(RrhhEfector::obtenerMedicosPorEfector(yii::$app->user->getIdEfector()), 'id_rr_hh', 'datos'),
                'theme' => 'bootstrap',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el Profesional...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);}
            ?>

        </div>
    </div>
    <div class="form-group">
        <div class="col-md-10 columns">
            <div class="col-xs-3">
                <?=
                        $form->field($model, 'fecha_inicio')->
                        widget(DatePicker::className(), [
                            'name' => 'check_issue_date',
                            'value' => date('Y-M-d', strtotime('+2 days')),
                            'options' => ['placeholder' => 'Seleccione Fecha de Inicio...'],
                            'pluginOptions' => [
                                'format' => 'yyyy-mm-dd',
                                'autoclose' => true,
                            ]
                ]);
                ?>
            </div>

            <div class="col-xs-2">
                <?=
                $form->field($model, 'hora_inicio')->widget(TimePicker::className(), [
                    'pluginOptions' => [
                        'defaultTime' => '06:00',
                        'showSeconds' => false,
                        'showMeridian' => false,
                        'minuteStep' => 15
                    ],
                ]);
                ?>
            </div>

            <div class="col-xs-1 form-group control-label">
                a
            </div>

            <div class="col-xs-3">
                <?=
                        $form->field($model, 'fecha_fin')->
                        widget(DatePicker::className(), [
                            'name' => 'check_issue_date',
                            'value' => date('Y-M-d', strtotime('+2 days')),
                            'options' => ['placeholder' => 'Seleccione Fecha de Fin...'],
                            'pluginOptions' => [
                                'format' => 'yyyy-mm-dd',
                                'autoclose' => true,
                            ]
                ]);
                ?>
            </div>

            <div class="col-xs-2">
                <?=
                $form->field($model, 'hora_fin')->widget(TimePicker::className(), [
                    'pluginOptions' => [
                        'defaultTime' => '20:00',
                        'showSeconds' => false,
                        'showMeridian' => false,
                        'minuteStep' => 15
                    ],
                ]);
                ?>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="col-md-10 columns">
            <div class="checkbox-inline">
                <?php ($model->lunes === 'SI')?$model->lunes = true:$model->lunes = false; ?>
                <?=
                        $form->field($model, 'lunes')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'lunes',
                            'value'=>1,
                            'pluginOptions' => [                            
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->martes === 'SI')?$model->martes = true:$model->martes = false; ?>
                <?=
                        $form->field($model, 'martes')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'martes',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->miercoles === 'SI')?$model->miercoles = true:$model->miercoles = false; ?>
                <?=
                        $form->field($model, 'miercoles')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'miercoles',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->jueves === 'SI')?$model->jueves = true:$model->jueves = false; ?>
                <?=
                        $form->field($model, 'jueves')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'jueves',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->viernes === 'SI')?$model->viernes = true:$model->viernes = false; ?>
                <?=
                        $form->field($model, 'viernes')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'viernes',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->sabado === 'SI')?$model->sabado = true:$model->sabado = false; ?>
                <?=
                        $form->field($model, 'sabado')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'sabado',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
            <div class="checkbox-inline">
                <?php ($model->domingo === 'SI')?$model->domingo = true:$model->domingo = false; ?>
                <?=
                        $form->field($model, 'domingo')->
                        widget(CheckboxX::className(), [
                            'model' => $model,
                            'attribute' => 'domingo',
                            'pluginOptions' => [
                                'threeState' => false,
                                'size' => 'xs',
                            ],
                ]);
                ?>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="col-md-10 columns">
            <div class="col-xs-2">
                <?= $form->field($model, 'id_tipo_dia')->DropDownList(ArrayHelper::map(Tipo_dia::find()->all(), 'id_tipo_dia', 'nombre'), ['prompt' => '']) ?>
            </div>

            <!-- <div class="col-xs-2"> -->
                <?php
                // echo $form->field($model, 'id_efector')->DropDownList(ArrayHelper::map(Efector::find()->all(), 'id_efector', 'nombre'), ['prompt' => 'Seleccione el Efector']) 
                ?>
            <!-- </div> -->

            <div class="col-xs-3">
                <?php echo $form->field($model, 'cupo_pacientes',  
                    ['addon' => ['append' => ['content'=>'', 'options' => ["style"=>"width:100px"]]]])
                    ->textInput(['type'=>'number']); ?>
            </div>            
        </div>
    </div>
    <div class="form-group">
        <div class="col-md-10 columns">
            <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
if($model->hora_inicio){
    $from = $model->hora_inicio;
    $to = $model->hora_fin;
    $total      = strtotime($to) - strtotime($from);
    $hours      = floor($total / 60 / 60);
    $minutes    = round(($total - ($hours * 60 * 60)) / 60);
}else{
    $hours = 14;
}

$up = !$model->isNewRecord?"calcular_tiempo(".$hours.", ".$model->cupo_pacientes.");":"";

$this->registerJs("
    $(document).on('change', '#agenda_rrhh-cupo_pacientes', function() {
        var horas = ".$hours.";
        // if (horas == 0){
        //     $().val();
        // }
        var cantidad = $(this).val();        
        calcular_tiempo(horas, cantidad);        
    });

    function calcular_tiempo(horas, cantidad){
        var tiempo_x_paciente = 0;
        if(cantidad !== 0){
            tiempo_x_paciente = horas/cantidad;
        }
        if(tiempo_x_paciente >= 1){
            var f = tiempo_x_paciente % 1;
            if(f != 0){tiempo_x_paciente = Math.floor(tiempo_x_paciente) + ':' + Math.round(f * 60);}
            if(tiempo_x_paciente == 1){
                t_texto = tiempo_x_paciente + 'h';
            }else{
                t_texto = tiempo_x_paciente + 'hs'
            }
        }else{
            t_texto = Math.round(tiempo_x_paciente * 60);
            t_texto += 'min';
        } 
        $('#agenda_rrhh-cupo_pacientes').parent().find('.input-group-addon').html(t_texto);    
    }  
".$up);
?>
