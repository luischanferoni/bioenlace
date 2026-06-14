<?php

/**
 * @var yii\web\View $this
 * @var common\models\User $user
 */

use yii\helpers\Html;

$this->title = 'E-mail confirmado';
?>
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-8 col-lg-6">
			<div class="alert alert-success text-center mb-4" role="alert">
				El e-mail <strong><?= Html::encode($user->email) ?></strong> quedó confirmado.
			</div>
			<div class="text-center">
				<?php if (Yii::$app->user->isGuest): ?>
					<?= Html::a('Iniciar sesión', ['/auth/login'], ['class' => 'btn btn-primary']) ?>
				<?php else: ?>
					<?= Html::a('Volver al inicio', Yii::$app->homeUrl, ['class' => 'btn btn-primary']) ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
