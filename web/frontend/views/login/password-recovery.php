<?php

/**
 * @var yii\web\View $this
 * @var common\models\forms\PasswordRecoveryForm $model
 */

use yii\bootstrap5\ActiveForm;
use yii\captcha\Captcha;
use yii\helpers\Html;

$this->title = 'Recuperar contraseña';
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
								<?php if (Yii::$app->session->hasFlash('error')): ?>
									<div class="alert alert-warning"><?= Yii::$app->session->getFlash('error') ?></div>
								<?php endif; ?>

								<?php $form = ActiveForm::begin([
									'id' => 'password-recovery-form',
									'options' => ['autocomplete' => 'off'],
								]) ?>

								<?= $form->field($model, 'email')->textInput(['maxlength' => 255, 'autofocus' => true]) ?>

								<?= $form->field($model, 'captcha')->widget(Captcha::class, [
									'template' => '<div class="row"><div class="col-sm-4">{image}</div><div class="col-sm-8">{input}</div></div>',
									'captchaAction' => ['/auth/captcha'],
								]) ?>

								<div class="d-grid mb-3">
									<?= Html::submitButton('Recuperar', ['class' => 'btn btn-primary']) ?>
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
