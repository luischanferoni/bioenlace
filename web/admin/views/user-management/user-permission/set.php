<?php
/**
 * @var yii\web\View $this
 * @var common\models\User $user
 */

use common\components\Platform\Core\Permission\RbacRoleQueryService;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

BootstrapPluginAsset::register($this);
$this->title = 'Roles del usuario: ' . $user->username;
$this->params['breadcrumbs'][] = ['label' => 'Usuarios', 'url' => ['/user-management/user/index']];
$this->params['breadcrumbs'][] = $this->title;

$userRoleNames = ArrayHelper::map(RbacRoleQueryService::getUserRoles($user->id), 'name', 'name');
$pesRolesByEfector = RbacRoleQueryService::listPesRolesByEfectorForUser((int) $user->id);
$multipleRoles = true;
if (Yii::$app->has('user-management')) {
    $module = Yii::$app->getModule('user-management');
    if ($module !== null && isset($module->userCanHaveMultipleRoles)) {
        $multipleRoles = (bool) $module->userCanHaveMultipleRoles;
    }
}
?>

<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

<?php if (Yii::$app->session->hasFlash('success')): ?>
	<div class="alert alert-success text-center">
		<?= Yii::$app->session->getFlash('success') ?>
	</div>
<?php endif; ?>

<?php if (Yii::$app->session->hasFlash('error')): ?>
	<div class="alert alert-warning text-center">
		<?= Yii::$app->session->getFlash('error') ?>
	</div>
<?php endif; ?>

<div class="row">
	<div class="col-sm-8">
		<div class="card mb-3">
			<div class="card-header">
				<strong>Roles por efector (PES)</strong>
				<span class="text-muted small ms-2">Solo lectura — se definen al asignar el profesional al servicio del centro</span>
			</div>
			<div class="card-body">
				<?php if ($pesRolesByEfector === []): ?>
					<p class="text-muted mb-0">
						Este usuario no tiene persona vinculada o no tiene asignaciones PES activas en ningún efector.
					</p>
				<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-striped mb-0">
							<thead>
								<tr>
									<th>Efector</th>
									<th>Servicio del centro</th>
									<th>Rol</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($pesRolesByEfector as $row): ?>
									<tr>
										<td>
											<?= Html::encode($row['efector_nombre'] !== '' ? $row['efector_nombre'] : ('#' . $row['id_efector'])) ?>
											<span class="text-muted small">(<?= (int) $row['id_efector'] ?>)</span>
										</td>
										<td><?= Html::encode($row['servicio_nombre'] !== '' ? $row['servicio_nombre'] : ('#' . $row['id_servicio'])) ?></td>
										<td><code><?= Html::encode($row['role_name']) ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header">
				<strong>Roles especiales (auth_assignment)</strong>
				<span class="text-muted small ms-2">Plataforma / multi-efector — editables abajo</span>
			</div>
			<div class="card-body">
				<?= Html::beginForm(['set-roles', 'id' => $user->id]) ?>

				<?php foreach (RbacRoleQueryService::getAvailableRoles() as $aRole): ?>
					<?php $roleName = (string) $aRole->name; ?>
					<?php $isChecked = isset($userRoleNames[$roleName]) ? 'checked' : ''; ?>
					<div class="form-check mb-2">
						<?php if ($multipleRoles): ?>
							<input class="form-check-input" type="checkbox" <?= $isChecked ?> name="roles[]" value="<?= Html::encode($roleName) ?>" id="role-<?= Html::encode($roleName) ?>">
						<?php else: ?>
							<input class="form-check-input" type="radio" <?= $isChecked ?> name="roles" value="<?= Html::encode($roleName) ?>" id="role-<?= Html::encode($roleName) ?>">
						<?php endif; ?>
						<label class="form-check-label" for="role-<?= Html::encode($roleName) ?>">
							<?= Html::encode($aRole->description ?: $roleName) ?>
						</label>
						<?= Html::a(
							'Permisos del rol (intents)',
							['/user-management/role/update', 'name' => $roleName],
							['class' => 'btn btn-link btn-sm', 'target' => '_blank']
						) ?>
					</div>
				<?php endforeach ?>

				<br>

				<?php if (Yii::$app->user->isSuperadmin || (int) Yii::$app->user->id !== (int) $user->id): ?>
					<?= Html::submitButton('Guardar', ['class' => 'btn btn-primary btn-sm']) ?>
				<?php else: ?>
					<div class="alert alert-warning well-sm text-center">
						No puede modificar sus propios roles.
					</div>
				<?php endif; ?>

				<?= Html::endForm() ?>
			</div>
		</div>
	</div>
	<div class="col-sm-4">
		<div class="alert alert-info">
			Los roles clínicos (<code>Medico</code>, <code>enfermeria</code>, …) vienen del
			<strong>servicio del PES</strong> en cada efector; no se tildan aquí.
			Los <strong>intents</strong> de roles especiales se administran en
			<?= Html::a('Roles RBAC', ['/user-management/role/index']) ?>.
			Los <strong>atributos</strong> en
			<?= Html::a('Catálogo de permisos', ['/permission-catalog/index']) ?>.
		</div>
	</div>
</div>
