<?php

use common\components\Legacy\UserManagementCompat;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\User $model
 */

$this->title = UserManagementCompat::t('back', 'User creation');
$this->params['breadcrumbs'][] = ['label' => UserManagementCompat::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-create">

	<h2 class="lte-hide-title"><?= $this->title ?></h2>

	<div class="panel panel-default">
		<div class="panel-body">
			<?= $this->render('_form', ['model' => $model]) ?>
			
		</div>
	</div>

</div>
