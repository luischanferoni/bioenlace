<?php
/**
 * @var yii\web\View $this
 * @var common\models\User $user
 */

use common\models\webvimark\moduleusermanagement\models\rbacDB\SisseRole;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

BootstrapPluginAsset::register($this);
$this->title = 'Roles del usuario: ' . $user->username;
$this->params['breadcrumbs'][] = ['label' => 'Usuarios', 'url' => ['/user-management/user/index']];
$this->params['breadcrumbs'][] = $this->title;

$userRoleNames = ArrayHelper::map(Role::getUserRoles($user->id), 'name', 'name');
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
				<strong>Roles asignados</strong>
			</div>
			<div class="card-body">
				<?= Html::beginForm(['set-roles', 'id' => $user->id]) ?>

				<?php foreach (SisseRole::getAvailableRoles() as $aRole): ?>
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
							'Permisos del rol',
							['/permission-catalog/edit-role', 'role' => $roleName],
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
			Los <strong>permisos</strong> se administran por rol en
			<?= Html::a('Catálogo de permisos', ['/permission-catalog/index']) ?>.
		</div>
	</div>
</div>
