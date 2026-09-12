<?php

use common\components\Platform\Core\Permission\BioenlaceGhostHtml;
use common\components\Platform\Core\Permission\RbacRoleQueryService;
use common\components\Platform\Ui\Grid\GridBulkActions;
use common\components\Platform\Ui\Grid\GridPageSize;
use common\components\Platform\Ui\Grid\StatusColumn;
use common\models\Platform\User;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

/* @var yii\web\View $this */
/* @var yii\data\ActiveDataProvider $dataProvider */
/* @var common\models\Platform\UserSearch $searchModel */

$this->title = 'Usuarios';
$this->params['breadcrumbs'][] = $this->title;

$roleFilter = RbacRoleQueryService::getAllRolesForFilter();
?>
<div class="user-index">


	<div class="card">

		<div class="card-body">

			<div class="row">
				<div class="col-sm-8">
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						Para agregar un usuario primero debe registrar los datos de la persona.
						Filtrá por rol (p. ej. paciente) para ver quién tiene acceso con ese perfil.
						<?= Html::a('Buscar Persona', ['/personas/index'], ['class' => 'btn btn-primary btn-sm ms-2']) ?>
					</div>
				</div>

				<div class="col-sm-4">
					<?= GridPageSize::widget(['pjaxId' => 'user-grid-pjax']) ?>
				</div>
			</div>


			<?php Pjax::begin([
				'id' => 'user-grid-pjax',
			]) ?>

			
				<?= GridView::widget([
					'id' => 'user-grid',
					'dataProvider' => $dataProvider,
					'tableOptions' => ['class' => 'table mb-0 dataTable table-responsive border rounded w-auto'],
					'headerRowOptions' => ['class' => 'bg-soft-primary'],
					'filterRowOptions' => ['class' => 'bg-white'],
					//'rowOptions'=>['class'=>'d-flex'],
					'pager' => ['class' => 'yii\bootstrap5\LinkPager', 'prevPageLabel' => 'Anterior', 'nextPageLabel' => 'Siguiente', 'options' => ['class' => 'pagination justify-content-center mt-5']],
					'filterModel' => $searchModel,
					'layout' => '{items}<div class="row"><div class="col-sm-8">{pager}</div><div class="col-sm-4 text-right">{summary}' . GridBulkActions::widget([
						'gridId' => 'user-grid',
						'actions' => [
							Url::to(['bulk-activate', 'attribute' => 'status']) => GridBulkActions::t('app', 'Activate'),
							Url::to(['bulk-deactivate', 'attribute' => 'status']) => GridBulkActions::t('app', 'Deactivate'),
							'----' => [
								Url::to(['bulk-delete']) => GridBulkActions::t('app', 'Delete'),
							],
						],
					]) . '</div></div>',
					'columns' => [
						['class' => 'yii\grid\CheckboxColumn', 'options' => ['style' => 'width:10px']],
						[
							'class' => StatusColumn::class,
							'attribute' => 'superadmin',
							'label'=>'SuperAdmin',
							'visible' => Yii::$app->user->isSuperadmin,
						],
						[
							'attribute' => 'persona_apellido',
							'label' => 'Apellido',
							'value' => static function (User $model) {
								return $model->persona->apellido ?? '';
							},
						],
						[
							'attribute' => 'persona_nombre',
							'label' => 'Nombre',
							'value' => static function (User $model) {
								return $model->persona->nombre ?? '';
							},
						],
						[
							'attribute' => 'persona_documento',
							'label' => 'Documento',
							'value' => static function (User $model) {
								return $model->persona->documento ?? '';
							},
						],
						[
							'label' => 'Username',
							'attribute' => 'username',
							'value' => function (User $model) {
								return Html::a($model->username, ['view', 'id' => $model->id], ['data-pjax' => 0]);
							},
							'format' => 'raw',
						],
						[
							'attribute' => 'email',
							'label' => 'Email',
							'format' => 'raw',
							'visible' => User::hasPermission('viewUserEmail'),
						],
						[
							'class' => StatusColumn::class,
							'attribute' => 'email_confirmed',
							'label' => 'Email confirmado',
							'visible' => User::hasPermission('viewUserEmail'),
						],
						[
							'attribute' => 'gridRoleSearch',
							'filter' => $roleFilter,
							'value' => function (User $model) {
								$names = ArrayHelper::getColumn($model->roles, 'name');
								if ($names === []) {
									return '<span class="text-muted">sin roles</span>';
								}

								return Html::encode(implode(', ', $names));
							},
							'format' => 'raw',
							'visible' => User::hasPermission('viewUserRoles'),
						],
						[
							'attribute' => 'registration_ip',
							'value' => function (User $model) {
								return Html::a($model->registration_ip, "http://ipinfo.io/" . $model->registration_ip, ["target" => "_blank"]);
							},
							'format' => 'raw',
							'visible' => false /*User::hasPermission('viewRegistrationIp')*/,
						],
						[
							'value' => function (User $model) {
								return BioenlaceGhostHtml::a(
									'Roles y permisos',
									['/user-management/user-permission/set', 'id' => $model->id],
									['class' => 'btn btn-sm btn-primary', 'data-pjax' => 0]
								)
									. '<br>' .
									BioenlaceGhostHtml::a(
										'Cambiar contraseña',
										['change-password', 'id' => $model->id],
										['class' => 'btn btn-sm btn-warning', 'data-pjax' => 0]
									)
									. '<br>' .
									BioenlaceGhostHtml::a(
										'Ingresar como este usuario',
										['/user/impersonate', 'id' => $model->id],
										['linkOptions' => ['target' => '_blank']],
										['class' => 'btn btn-sm btn-success', 'data-pjax' => 0]
									);									
							},
							'format' => 'raw',
							'visible' => User::canRoute('/user-management/user-permission/set'),
							'options' => [
								'width' => '10px',
							],
						],
						[
							'class' => StatusColumn::class,
							'attribute' => 'status',
							'optionsArray' => [
								[User::STATUS_ACTIVE, 'Activo', 'success'],
								[User::STATUS_INACTIVE, 'Inactivo', 'warning'],
								[User::STATUS_BANNED, 'Baneado', 'danger'],
							],
						],
						[
							'class' => 'yii\grid\ActionColumn'
						],
					],
				]); ?>

			<?php Pjax::end() ?>

		</div>
	</div>
</div>
