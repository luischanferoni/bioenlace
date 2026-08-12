<?php

/**
 * @var $this yii\web\View
 * @var $model common\models\LoginForm
 */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
?>

<div class="container-fluid">
	<section class="login-content overflow-hidden">
		<div class="row no-gutters align-items-center bg-white">
			<div class="col-md-12 col-lg-6 align-self-center">
				<img
					src="<?= Yii::getAlias('@web') ?>/images/logo.svg"
					alt="Bioenlace"
					class="login-brand-logo mb-4 mt-5"
					width="240"
					height="51"
				>
				<div class="row justify-content-center pt-3">
					<div class="col-md-9">
						<div class="card d-flex justify-content-center mb-0 auth-card iq-auth-form">
							<div class="card-body">
								<h2 class="mb-5 text-center">Inicio de Sesión</h2>

								<?php $form = ActiveForm::begin([
									'id'      => 'login-form',
									'options' => ['autocomplete' => 'off'],
									'validateOnBlur' => false,
									'fieldConfig' => [
										'template' => "{input}\n{error}",
									],
								]) ?>

								<?= $form->field($model, 'username')
									->textInput(['placeholder' => $model->getAttributeLabel('username'), 'autocomplete' => 'off']) ?>

								<?= $form->field($model, 'password')
									->passwordInput(['placeholder' => $model->getAttributeLabel('password'), 'autocomplete' => 'off']) ?>

								<?= (isset(Yii::$app->user->enableAutoLogin) && Yii::$app->user->enableAutoLogin) ? $form->field($model, 'rememberMe')->checkbox(['value' => true]) : '' ?>

								<div class="d-flex justify-content-center">
									<?= Html::submitButton(
										'Ingresar',
										['class' => 'btn btn-primary mb-5']
									) ?>
								</div>
								<?php if (Yii::$app->session->hasFlash('info')): ?>
									<div class="alert alert-right alert-warning alert-dismissible fade show mb-3" role="alert">
										<span><i class="fas fa-bell"></i></span>
										<span><?= Yii::$app->session->getFlash('info') ?></span>
										<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
									</div>
								<?php endif;?>

								<div class="row registration-block">
									<div class="col-sm-6">
										<?= Html::a('Activar cuenta nueva', ['/auth/activate-account']) ?>
									</div>
									<div class="col-sm-6 text-end">
										<?= Html::a(
											'¿Olvidó su contraseña?',
											['/auth/password-recovery']
										) ?>
									</div>
								</div>

								<?php ActiveForm::end() ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-lg-6 pe-0">
				<div class="login-side-pattern" aria-hidden="true"></div>
			</div>
		</div>
	</section>
</div>
