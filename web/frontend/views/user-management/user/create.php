<?php

use common\components\Platform\Legacy\UserManagementCompat;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\User $model
 */

$this->title = UserManagementCompat::t('back', 'User creation');
$this->params['breadcrumbs'][] = ['label' => UserManagementCompat::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">
		<?= $this->render('_form', ['model' => $model]) ?>
	</div>

</div>
