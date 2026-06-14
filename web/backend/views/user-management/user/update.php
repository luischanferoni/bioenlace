<?php

use common\components\Legacy\UserManagementCompat;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\User $model
 */

$this->title = UserManagementCompat::t('back', 'Editing user: ') . ' ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => UserManagementCompat::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->username, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = UserManagementCompat::t('back', 'Editing');
?>
<div class="user-update">

	<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

	<div class="panel panel-default">
		<div class="panel-body">
			<?= $this->render('_form', ['model' => $model]) ?>
		</div>
	</div>

</div>
