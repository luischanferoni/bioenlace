<?php

/**
 * @var yii\web\View $this
 * @var common\models\forms\ActivateAccountForm $model
 */

use yii\bootstrap5\ActiveForm;
use yii\captcha\Captcha;
use yii\helpers\Html;

$this->title = 'Activar cuenta';
?>
<div class="container-fluid">
	<section class="login-content overflow-hidden">
		<div class="row no-gutters align-items-center bg-white">
			<div class="col-md-12 col-lg-6 align-self-center py-5">
				<h2 class="text-center mb-4"><?= Html::encode($this->title) ?></h2>
				<div class="row justify-content-center">
					<div class="col-md-9">
						<div class="card auth-card">
							<div class="card-body">
								<p class="text-muted">Ingresá el usuario y el código que te entregó administración de tu centro.</p>

								<?php $form = ActiveForm::begin([
									'id' => 'activate-account-form',
									'options' => ['autocomplete' => 'off'],
								]) ?>

								<?= $form->field($model, 'username')->textInput(['maxlength' => 255, 'autofocus' => true]) ?>
								<?= $form->field($model, 'activation_code')->textInput(['maxlength' => 10, 'autocomplete' => 'off']) ?>
								<?= $form->field($model, 'password')->passwordInput(['maxlength' => 255, 'autocomplete' => 'new-password']) ?>
								<?= $form->field($model, 'repeat_password')->passwordInput(['maxlength' => 255, 'autocomplete' => 'new-password']) ?>

								<?= $form->field($model, 'captcha')->widget(Captcha::class, [
									'template' => '<div class="row"><div class="col-sm-4">{image}</div><div class="col-sm-8">{input}</div></div>',
									'captchaAction' => ['/auth/captcha'],
								]) ?>

								<div class="d-grid mb-3">
									<?= Html::submitButton('Activar cuenta', ['class' => 'btn btn-primary']) ?>
								</div>

								<div class="text-center">
									<?= Html::a('Volver al login', ['/auth/login']) ?>
								</div>

								<?php ActiveForm::end() ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg-6 pe-0 d-none d-lg-block">
				<div class="img-fluid" style="background-image: url('<?= Yii::getAlias('@web') ?>/images/portada.jpg'); min-height: 100vh"></div>
			</div>
		</div>
	</section>
</div>
