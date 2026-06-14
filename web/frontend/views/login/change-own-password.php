<?php

/**
 * @var yii\web\View $this
 * @var common\models\forms\ChangeOwnPasswordForm $model
 */

use common\models\forms\ChangeOwnPasswordForm;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = 'Cambiar contraseña';
?>
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-8 col-lg-6">
			<div class="card auth-card">
				<div class="card-body">
					<h2 class="mb-4 text-center"><?= Html::encode($this->title) ?></h2>

					<?php $form = ActiveForm::begin([
						'id' => 'change-own-password-form',
						'options' => ['autocomplete' => 'off'],
					]) ?>

					<?php if ($model->scenario !== ChangeOwnPasswordForm::SCENARIO_RESTORE_VIA_EMAIL): ?>
						<?= $form->field($model, 'current_password')->passwordInput(['autocomplete' => 'off']) ?>
					<?php endif; ?>

					<?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'off']) ?>
					<?= $form->field($model, 'repeat_password')->passwordInput(['autocomplete' => 'off']) ?>

					<div class="d-grid">
						<?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
					</div>

					<?php ActiveForm::end() ?>
				</div>
			</div>
		</div>
	</div>
</div>
