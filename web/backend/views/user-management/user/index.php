<?php

use common\components\Core\Permission\BioenlaceGhostHtml;
use common\components\Core\Permission\RbacRoleQueryService;
use common\components\Legacy\UserManagementCompat;
use common\components\Ui\Grid\GridBulkActions;
use common\components\Ui\Grid\GridPageSize;
use common\components\Ui\Grid\StatusColumn;
use common\models\User;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var common\models\search\UserSearch $searchModel
 */

$this->title = UserManagementCompat::t('back', 'Users');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-index">


	<div class="card">

		<div class="card-body">

			<div class="row">
				<div class="col-sm-8">
					<div class="alert alert-info alert-dismissable">
						<button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>
						<i class="glyphicon glyphicon-info-sign"></i> Para agregar un usuario primero debe registrar los datos de la persona

						<?= Html::a('Buscar Persona', ['/personas/index'], ['class' => 'btn btn-primary']) ?>
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
							'filter' => ArrayHelper::map(RbacRoleQueryService::getAvailableRoles(Yii::$app->user->isSuperadmin), 'name', 'description'),
							'value' => function (User $model) {
								return implode('</br>', ArrayHelper::map($model->roles, 'name', 'name'));
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
									UserManagementCompat::t('back', 'Roles and permissions'),
									['/user-management/user-permission/set', 'id' => $model->id],
									['class' => 'btn btn-sm btn-primary', 'data-pjax' => 0]
								)
									. '<br>' .
									BioenlaceGhostHtml::a(
										UserManagementCompat::t('back', 'Change password'),
										['change-password', 'id' => $model->id],
										['class' => 'btn btn-sm btn-warning', 'data-pjax' => 0]
									)
									. '<br>' .
									BioenlaceGhostHtml::a(
										UserManagementCompat::t('back', 'Log in as this user'),
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
								[User::STATUS_ACTIVE, UserManagementCompat::t('back', 'Active'), 'success'],
								[User::STATUS_INACTIVE, UserManagementCompat::t('back', 'Inactive'), 'warning'],
								[User::STATUS_BANNED, UserManagementCompat::t('back', 'Banned'), 'danger'],
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