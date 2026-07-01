<?php

use common\models\User;
use common\components\Platform\Legacy\UserManagementCompat;
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
		->dropDownList(User::getStatusList()) ?>

	<?= $form->field($model, 'username')->textInput(['maxlength' => 255, 'autocomplete'=>'off']) ?>

	<?php if ( $model->isNewRecord ): ?>
		<p class="help-block col-sm-offset-3 col-sm-9 text-muted">
			No defina contraseña aquí: tras crear el usuario enviará invitación por e-mail o código presencial.
		</p>
	<?php endif; ?>
	<?php if ( User::hasPermission('bindUserToIp') ): ?>

		<?= $form->field($model, 'bind_to_ip')
			->textInput(['maxlength' => 255])
			->hint(UserManagementCompat::t('back','For example: 123.34.56.78, 168.111.192.12')) ?>

	<?php endif; ?>

	<?php if ( User::hasPermission('editUserEmail') || $model->isNewRecord ): ?>

		<?= $form->field($model, 'email')->textInput(['maxlength' => 255]) ?>
		<?php if (!$model->isNewRecord): ?>
		<?= $form->field($model, 'email_confirmed')->checkbox() ?>
		<?php endif; ?>

	<?php endif; ?>


	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<?php if ( $model->isNewRecord ): ?>
				<?= Html::submitButton(
					'<span class="glyphicon glyphicon-plus-sign"></span> ' . UserManagementCompat::t('back', 'Nuevo'),
					['class' => 'btn btn-success']
				) ?>
			<?php else: ?>
				<?= Html::submitButton(
					'<span class="glyphicon glyphicon-ok"></span> ' . UserManagementCompat::t('back', 'Save'),
					['class' => 'btn btn-primary']
				) ?>
			<?php endif; ?>
		</div>
	</div>

	<?php ActiveForm::end(); ?>

</div>