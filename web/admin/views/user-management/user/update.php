<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var common\models\Platform\User $model
 */

$this->title = 'Editar usuario: ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => 'Usuarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->username, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Edición';
?>
<div class="user-update">

	<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

	<div class="panel panel-default">
		<div class="panel-body">

			<?= $this->render('_form', ['model' => $model]) ?>
		</div>
	</div>

</div>
