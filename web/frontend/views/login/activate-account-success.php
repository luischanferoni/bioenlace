<?php

/**
 * @var yii\web\View $this
 */

use yii\helpers\Html;

$this->title = 'Cuenta activada';
?>
<div class="container-fluid">
	<section class="login-content overflow-hidden">
		<div class="row no-gutters align-items-center bg-white">
			<div class="col-md-12 col-lg-6 align-self-center py-5">
				<div class="row justify-content-center">
					<div class="col-md-9">
						<div class="card auth-card">
							<div class="card-body text-center">
								<h2><?= Html::encode($this->title) ?></h2>
								<p>Ya podés ingresar con tu usuario y la contraseña que elegiste.</p>
								<?= Html::a('Ir al login', ['/auth/login'], ['class' => 'btn btn-primary']) ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
