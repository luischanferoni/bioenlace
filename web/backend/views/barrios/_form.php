<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use common\models\Departamento;
use common\models\Provincia;
use common\models\Localidad;
use kartik\select2\Select2;


use kartik\depdrop\DepDrop;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Barrios */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="barrios-form">

<?php if($model->isNewRecord){ ?>
	<div class="row">
		<div class="col-xs-12">Por favor, seleccione previamente la provincia, departamento y localidad</div>

	</div>
<?php } else { ?>
	<h4><?php echo "Provincia: ".$model->localidad->departamento->provincia->nombre.", departamento: ".$model->localidad->departamento->nombre.", localidad: ".$model->localidad->nombre?></h4>
<?php } ?>
	<?php $form = ActiveForm::begin(); 
	?>
	<?php if($model->isNewRecord){ ?>
		<div class="col-sm-6">
                <?php
                $provincia = ArrayHelper::map(Provincia::find()->asArray()->all(), 'id_provincia', 'nombre');

				// Without model and implementing a multiple select
				echo '<label class="control-label">Provincia</label>';
				echo Select2::widget([
					'name' => 'provincia',
					'data' => $provincia,
                    'theme'=>'default',
					'options' => [
						'placeholder' => 'Seleccione una provincia',
					],
					'pluginOptions' => [
                        'allowClear' => true,
                        'width' => '100%'
                    ],
				]);
              
              
                ?>
        </div>
            <?php
            //******************************************************************
            // Select dependiente de DEPARTAMENTOS
            ?>
            <div class="col-sm-6">
			<label class="control-label">Departamento</label>

			<?php echo DepDrop::widget([
					'name' => 'departamento',
					'options' => ['id'=>'id_departamento'],
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options'=>['theme'=>'default','pluginOptions'=>['width' => '100%']],
					'pluginOptions' => [
						'depends'  => ['w1'],
						'placeholder' => 'Seleccione Departamento',
						'url' => Url::to(['/personas/subcat'])
					]
				]);  
			?>
            
            </div>
       
            <div class="col-sm-6">
			<label class="control-label">Localidad</label>
                <?php
                echo $form->field($model, 'id_localidad')->widget(DepDrop::classname(), [
                    'type' => DepDrop::TYPE_SELECT2,
                    'select2Options'=>['theme'=>'default', 'pluginOptions'=>['width' => '100%']],
                    'pluginOptions' => [
                        'depends' => ['id_departamento'],
                        'placeholder' => 'Seleccione Localidad',
                        'url' => Url::to(['/personas/loc'])
                    ]
                ])->label(false);
                ?>
            </div>
			<?php } ?>
		<?php if(!$model->isNewRecord){ ?>
			<?= $form->field($model, 'id_localidad')->hiddenInput(['value'=> $localidad->id_localidad])->label(false); ?>
		<?php } ?>
		<div class="col-xs-12">
	    	<?= $form->field($model, 'nombre')->textInput(['maxlength' => true]) ?>
	    </div>

	    <div class="form-group">
	        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
	    </div>    

    <?php ActiveForm::end(); ?> 


</div>