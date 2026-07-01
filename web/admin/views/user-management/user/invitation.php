<?php

use common\components\Platform\Core\Auth\StaffAccountInvitationService;
use common\components\Platform\Legacy\UserManagementCompat;
use common\models\User;
use common\models\UserAccountInvitationLog;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var User $model
 * @var list<\common\models\UserAccountInvitationLog> $logs
 * @var string|null $continueUrl
 */

$this->title = 'Activar acceso: ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => UserManagementCompat::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->username, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Invitación';

$pending = StaffAccountInvitationService::isPendingActivation($model);
$codeFlash = Yii::$app->session->getFlash('activation_code_plain');
$emailFlash = Yii::$app->session->getFlash('invitation_email_status');
?>
<div class="user-invitation">

	<h2><?= Html::encode($this->title) ?></h2>

	<?php if ($emailFlash): ?>
		<div class="alert alert-info"><?= Html::encode((string) $emailFlash) ?></div>
	<?php endif; ?>

	<?php if ($codeFlash): ?>
		<div class="alert alert-warning">
			<strong>Código de activación (mostrar una sola vez al personal):</strong>
			<p class="fs-4 mb-0"><code><?= Html::encode((string) $codeFlash) ?></code></p>
			<p class="mb-0 small text-muted">Válido <?= (int) ceil(StaffAccountInvitationService::activationCodeExpireSeconds() / 3600) ?> horas.</p>
		</div>
	<?php endif; ?>

	<div class="panel panel-default mb-4">
		<div class="panel-body">
			<p><strong>Usuario:</strong> <?= Html::encode($model->username) ?></p>
			<p><strong>E-mail:</strong> <?= Html::encode((string) $model->email) ?: '—' ?></p>
			<p><strong>Estado:</strong>
				<?php if ($pending): ?>
					<span class="label label-warning">Pendiente de activación</span>
				<?php else: ?>
					<span class="label label-success">Cuenta activada</span>
				<?php endif; ?>
			</p>
		</div>
	</div>

	<?php if ($pending): ?>
		<div class="d-flex flex-wrap gap-2 mb-4">
			<?= Html::beginForm(['invitation-send-email', 'id' => $model->id], 'post', ['class' => 'd-inline']) ?>
				<?= Html::submitButton('Enviar invitación por e-mail', ['class' => 'btn btn-primary']) ?>
			<?= Html::endForm() ?>

			<?= Html::beginForm(['invitation-generate-code', 'id' => $model->id], 'post', ['class' => 'd-inline']) ?>
				<?= Html::submitButton('Generar código presencial', ['class' => 'btn btn-outline-secondary']) ?>
			<?= Html::endForm() ?>

			<?= Html::beginForm(['invitation-revoke', 'id' => $model->id], 'post', [
				'class' => 'd-inline',
				'data-confirm' => '¿Revocar invitaciones pendientes (link y código)?',
			]) ?>
				<?= Html::submitButton('Revocar invitación', ['class' => 'btn btn-outline-danger']) ?>
			<?= Html::endForm() ?>
		</div>

		<p class="text-muted">
			El personal puede activar la cuenta en
			<?= Html::a('la web', ['/auth/activate-account'], ['target' => '_blank']) ?>
			con el código, o desde la app Personal de Salud.
		</p>
	<?php endif; ?>

	<?php if ($continueUrl !== null && $continueUrl !== ''): ?>
		<p><?= Html::a('Continuar con la asignación →', $continueUrl, ['class' => 'btn btn-success']) ?></p>
	<?php else: ?>
		<p><?= Html::a('Volver al usuario', ['view', 'id' => $model->id], ['class' => 'btn btn-default']) ?></p>
	<?php endif; ?>

	<h3>Historial</h3>
	<table class="table table-striped table-sm">
		<thead>
			<tr>
				<th>Fecha</th>
				<th>Acción</th>
				<th>Detalle</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($logs === []): ?>
				<tr><td colspan="3">Sin registros.</td></tr>
			<?php else: ?>
				<?php foreach ($logs as $log): ?>
					<tr>
						<td><?= Yii::$app->formatter->asDatetime($log->created_at) ?></td>
						<td><?= Html::encode($log->action) ?></td>
						<td><code class="small"><?= Html::encode((string) $log->meta) ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
