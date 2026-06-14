<?php

use common\components\Legacy\UserManagementCompat;
use common\models\User;
use yii\bootstrap\ActiveForm;
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
<div class="card">

	<div class="card-header">
		<h2 class="lte-hide-title"><?= $this->title ?></h2>
	</div>

	<div class="card-body">
		<?= $this->render('_form', compact('model')) ?>
	</div>

</div>