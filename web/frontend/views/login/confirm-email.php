<?php

/**
 * @var yii\web\View $this
 * @var common\models\forms\ConfirmEmailForm $model
 */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = 'Confirmar e-mail';
?>
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-8 col-lg-6">
			<div class="card auth-card">
				<div class="card-body">
					<h2 class="mb-4 text-center"><?= Html::encode($this->title) ?></h2>

					<?php if (Yii::$app->session->hasFlash('error')): ?>
						<div class="alert alert-warning"><?= Yii::$app->session->getFlash('error') ?></div>
					<?php endif; ?>

					<?php if ($model->user->confirmation_token === null): ?>
						<?php $form = ActiveForm::begin([
							'id' => 'confirm-email-form',
							'options' => ['autocomplete' => 'off'],
						]) ?>

						<?= $form->field($model, 'email')->textInput(['maxlength' => 255, 'autofocus' => true]) ?>

						<div class="d-grid">
							<?= Html::submitButton('Enviar enlace de confirmación', ['class' => 'btn btn-primary']) ?>
						</div>

						<?php ActiveForm::end() ?>
					<?php else: ?>
						<div class="alert alert-info text-center" role="alert">
							Se envió un enlace de activación a
							<strong><?= Html::encode($model->user->email) ?></strong>.
							Expira en <?= (int) $model->getTokenTimeLeft(true) ?> min.
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
