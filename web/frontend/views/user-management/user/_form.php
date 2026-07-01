<?php

use common\components\Platform\Legacy\UserManagementCompat;
use common\models\User;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/**
 * @var yii\web\View $this
 * @var common\models\User $model
 * @var yii\bootstrap\ActiveForm $form
 */
?>

<div class="user-form">

	<?php $form = ActiveForm::begin([
		'id'=>'user',
		'layout'=>'horizontal',
		'validateOnBlur' => false,
	]); ?>

	
	<?= $form->field($model->loadDefaultValues(), 'status')
		->dropDownList(User::getStatusList(),['disabled' => true]) ?>

	<?php if ( !$model->isNewRecord ){ ?>	

	<?= $form->field($model, 'username')->textInput(['maxlength' => 255, 'autocomplete'=>'off', 'disabled' => true]) ?>

	<?php }else{?>

	<?= $form->field($model, 'username')->textInput(['maxlength' => 255, 'autocomplete'=>'off']) ?>

	<?php }?>	

	<?php if ( $model->isNewRecord ): ?>

		<p class="help-block text-muted">
			Tras crear el usuario podrá enviar invitación por e-mail o entregar un código presencial.
		</p>
		
	<?php endif; ?>


	<?php if ( User::hasPermission('editUserEmail') || $model->isNewRecord ): ?>

		<?= $form->field($model, 'email')->textInput(['maxlength' => 255]) ?>
		<?php if (!$model->isNewRecord): ?>
		<?= $form->field($model, 'email_confirmed')->checkbox() ?>
		<?php endif; ?>

	<?php endif; ?>


	<div class="form-group">
		<div class="float-end pe-4">
			<?php if ( $model->isNewRecord ): ?>
				<?= Html::submitButton(
					'<span class="glyphicon glyphicon-plus-sign"></span> ' . UserManagementCompat::t('back', 'Crear'),
					['class' => 'btn btn-success rounded-pill']
				) ?>
			<?php else: ?>
				<?= Html::submitButton(
					'<span class="glyphicon glyphicon-ok"></span> ' . UserManagementCompat::t('back', 'Save'),
					['class' => 'btn btn-primary rounded-pill']
				) ?>
			<?php endif; ?>
		</div>
	</div>

	<?php ActiveForm::end(); ?>

</div>