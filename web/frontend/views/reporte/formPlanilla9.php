<?php
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\depdrop\DepDrop;
use yii\helpers\Url;
use common\models\Consulta;
use yii\widgets\ActiveForm;
?>

<div class="row">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div class="header-title">
                <h2 class="card-title">Planilla 9- REPORTE DE ODONTOLOGIA</h2>
                <h4>Especifique los siguientes datos</h4>
            </div>
        </div>
        <div class="card-body">
                <?php $form = ActiveForm::begin([]);?>
                    <div class="row">                    
                        <div class="col">
                            <input type="hidden" id="idEfector" name="idEfector"  value="<?= $idEfector ?>" />
                            <input type="hidden" id="servicio" name="servicio"  value="<?= 2 ?>" />
                            
                            <?php                 
                            echo DatePicker::widget([
                                'name' => 'fecha',
                                'type' => DatePicker::TYPE_INPUT,
                                'value' => Date('Y-m'),
                                'pluginOptions' => [
                                    'autoclose' => true,
                                    'format' => 'yyyy-mm'
                                ]
                            ]);                        
                            ?>
                        </div>
                 
                    </div>
    <div class="form-group d-flex justify-content-end">
        <button type="submit" formtarget="_blank" class="btn btn-primary">Consultar</button>    
    </div>
<?php ActiveForm::end(); ?>
<div class="row">
    <?php if($mensaje != '') { ?>
            <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                <span><i class="fas fa-bell"></i></span>
                <span> <?= $mensaje ; ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>         
    <?php  }  ?>
</div>


            </div>
        </div>
    </div>
</div>

