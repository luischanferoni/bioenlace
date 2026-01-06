<?php

/**
 * @var $this yii\web\View
 * @var $model webvimark\modules\UserManagement\models\forms\LoginForm
 */

use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
?>

<div class="container-fluid">
	<section class="login-content overflow-hidden">
		<div class="row no-gutters align-items-center bg-white">
			<div class="col-md-12 col-lg-6 align-self-center">
				<img src="<?= Yii::getAlias('@web')?>/images/nuevo_logo.webp" width="160px" alt="" class="mx-auto d-block mb-5 mt-5 img-fluid">
				<h3 class="text-center mb-5">BIOENLACE</h3>
				<div class="row justify-content-center pt-5">
					<div class="col-md-9">
						<div class="card  d-flex justify-content-center mb-0 auth-card iq-auth-form">
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
										UserManagementModule::t('front', 'Ingresar'),
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
										<?= GhostHtml::a(
											UserManagementModule::t('front', "Registration"),
											['/user-management/auth/registration']
										) ?>
									</div>
									<div class="col-sm-6 text-right">
										<?= GhostHtml::a(
											UserManagementModule::t('front', "Forgot password ?"),
											['/user-management/auth/password-recovery']
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
				<div class="img-fluid" style="background-image: url('<?=Yii::getAlias('@web')?>/images/portada.jpg');height: 100vh">
					<svg
						data-name="Layer 1"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 1200 120"
						preserveAspectRatio="none"
						style="
							opacity: 0.7;
							width: 120%;
							height: 300px;
							fill: #85b9da;
							transform: rotateY(180deg);">
						<path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"></path>
					</svg>
				</div>
			</div>
	</section>
</div>