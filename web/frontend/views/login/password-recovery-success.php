<?php

/**
 * @var yii\web\View $this
 */

use yii\helpers\Html;

$this->title = 'Recuperar contraseña';
?>
<div class="container-fluid">
	<section class="login-content py-5">
		<div class="row justify-content-center">
			<div class="col-md-8 col-lg-6">
				<div class="alert alert-success text-center" role="alert">
					Revise su correo electrónico para continuar con el restablecimiento de contraseña.
				</div>
				<div class="text-center">
					<?= Html::a('Volver al login', ['/auth/login'], ['class' => 'btn btn-primary']) ?>
				</div>
			</div>
		</div>
	</section>
</div>
