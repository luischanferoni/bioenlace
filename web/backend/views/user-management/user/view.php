<?php

use common\components\Core\Permission\BioenlaceGhostHtml;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use common\models\User;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var common\models\User $model
 */

$this->title = 'Administrar Usuario';
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-view">
	<div class="card">
		<div class="card-header">
			<h2 class="lte-hide-title"><?= $model->username ?></h2>
		</div>

		<div class="card-body">

			<p>
				<?= BioenlaceGhostHtml::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']) ?>
				<?= BioenlaceGhostHtml::a('Nuevo', ['create'], ['class' => 'btn btn-sm btn-success']) ?>
				<?= BioenlaceGhostHtml::a(
					'Cambiar contraseña',
					['change-password', 'id' => $model->id],
					['class' => 'btn btn-sm btn-default', 'data-pjax' => 0]
				) ?>
				<?= BioenlaceGhostHtml::a(
					'Administracion de Efector',
					['/profesional-efector-servicio/create-admin-efector-desde-usuario', 'id' => $model->id],
					['class' => 'btn btn-sm btn-warning', 'data-pjax' => 0]
				) ?>
				<?= BioenlaceGhostHtml::a(
					'Editar roles',
					['/user-management/user-permission/set', 'id' => $model->id],
					['class' => 'btn btn-sm btn-default']
				) ?>

				<?php /*
				<?= GhostHtml::a(UserManagementModule::t('back', 'Delete'), ['delete', 'id' => $model->id], [
					'class' => 'btn btn-sm btn-danger pull-right',
					'data' => [
						'confirm' => UserManagementModule::t('back', 'Are you sure you want to delete this user?'),
						'method' => 'post',
					],
				]) ?>
				*/?>

			</p>

			<?= DetailView::widget([
				'model'      => $model,
				'attributes' => [
					'id',
					[
						'attribute' => 'status',
						'value' => User::getStatusValue($model->status),
					],
					'username',
					[
						'attribute' => 'email',
						'value' => $model->email,
						'format' => 'email',
						'visible' => User::hasPermission('viewUserEmail'),
					],
					[
						'attribute' => 'email_confirmed',
						'value' => $model->email_confirmed,
						'format' => 'boolean',
						'visible' => User::hasPermission('viewUserEmail'),
					],
					[
						'label' => 'Roles',
						'value' => implode('<br>', ArrayHelper::map(Role::getUserRoles($model->id), 'name', 'description')),
						'visible' => User::hasPermission('viewUserRoles'),
						'format' => 'raw',
					],
					[
						'attribute' => 'bind_to_ip',
						'visible' => User::hasPermission('bindUserToIp'),
					],
					array(
						'attribute' => 'registration_ip',
						'value' => Html::a($model->registration_ip, "http://ipinfo.io/" . $model->registration_ip, ["target" => "_blank"]),
						'format' => 'raw',
						'visible' => User::hasPermission('viewRegistrationIp'),
					),
					'created_at:datetime',
					'updated_at:datetime',
				],
			]) ?>

		</div>
	</div>
</div>